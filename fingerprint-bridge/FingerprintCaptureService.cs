using DPUruNet;
using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Drawing.Imaging;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading;
using System.Threading.Tasks;

namespace FingerprintBridge
{
    public sealed class FingerprintCaptureService
    {
        private readonly ISynchronizeInvoke _uiInvoker;
        private readonly SemaphoreSlim _captureLock = new SemaphoreSlim(1, 1);

        public FingerprintCaptureService(ISynchronizeInvoke uiInvoker)
        {
            _uiInvoker = uiInvoker;
        }

        public async Task<object> GetReadersSnapshotAsync()
        {
            var readers = await InvokeOnUiThreadAsync(() => ReaderCollection.GetReaders()).ConfigureAwait(false);

            return readers
                .Cast<Reader>()
                .Select(reader => new
                {
                    name = reader.Description.SerialNumber,
                    serial = reader.Description.SerialNumber,
                    display = reader.Description.SerialNumber
                })
                .ToList();
        }

        public async Task<object> CaptureAsync(CancellationToken cancellationToken)
        {
            if (!await _captureLock.WaitAsync(0, cancellationToken).ConfigureAwait(false))
            {
                throw new InvalidOperationException("A fingerprint scan is already in progress. Wait for it to finish, then try again.");
            }

            try
            {
                LogCaptureStage("Capture requested.");
                var readers = await InvokeOnUiThreadAsync(() => ReaderCollection.GetReaders()).ConfigureAwait(false);
                if (readers.Count == 0)
                {
                    throw new InvalidOperationException("No DigitalPersona readers were detected.");
                }

                var reader = readers[0];
                var captureResult = await CaptureSingleFingerprintAsync(reader, cancellationToken).ConfigureAwait(false);
                LogCaptureStage("Capture received; extracting image and template.");
                var bitmap = CreateBitmapFromCapture(captureResult);
                var fingerprintTemplateXml = CreateFingerprintTemplateXml(captureResult);

                if (bitmap == null)
                {
                    throw new InvalidOperationException("Fingerprint capture returned no image.");
                }

                using (bitmap)
                using (var stream = new MemoryStream())
                {
                    bitmap.Save(stream, ImageFormat.Png);
                    string base64 = Convert.ToBase64String(stream.ToArray());

                    return new
                    {
                        success = true,
                        message = "Fingerprint captured successfully.",
                        imageDataUrl = "data:image/png;base64," + base64,
                        fingerprintTemplateXml = fingerprintTemplateXml,
                        reader = reader.Description.SerialNumber,
                        capturedAt = DateTime.UtcNow.ToString("o")
                    };
                }
            }
            finally
            {
                LogCaptureStage("Capture request finished.");
                _captureLock.Release();
            }
        }

        private Task<CaptureResult> CaptureSingleFingerprintAsync(Reader reader, CancellationToken cancellationToken)
        {
            var tcs = new TaskCompletionSource<CaptureResult>(TaskCreationOptions.RunContinuationsAsynchronously);
            Reader.CaptureCallback? callback = null;

            var cancellationRegistration = cancellationToken.Register(() => tcs.TrySetCanceled(cancellationToken));

            try
            {
                callback = captureResult =>
                {
                    LogCaptureStage("Capture callback: " + captureResult.ResultCode + ", quality=" + captureResult.Quality);
                    try
                    {
                        if (!CheckCaptureResult(captureResult))
                        {
                            return;
                        }

                        tcs.TrySetResult(captureResult);
                    }
                    catch (Exception ex)
                    {
                        tcs.TrySetException(ex);
                    }
                };

                reader = StartCaptureOnUiThread(reader, callback);
            }
            catch
            {
                cancellationRegistration.Dispose();
                SafeDisposeReaderOnUiThread(reader, callback);
                throw;
            }

            return WaitForCaptureAsync(reader, callback, tcs, cancellationToken, cancellationRegistration);
        }

        private async Task<CaptureResult> WaitForCaptureAsync(
            Reader reader,
            Reader.CaptureCallback? callback,
            TaskCompletionSource<CaptureResult> tcs,
            CancellationToken cancellationToken,
            CancellationTokenRegistration cancellationRegistration)
        {
            try
            {
                var timeoutTask = Task.Delay(TimeSpan.FromSeconds(30), cancellationToken);
                var completed = await Task.WhenAny(tcs.Task, timeoutTask).ConfigureAwait(false);

                if (completed != tcs.Task)
                {
                    cancellationToken.ThrowIfCancellationRequested();
                    LogCaptureStage("Capture timed out.");
                    throw new TimeoutException("Fingerprint capture timed out. Place your finger flat on the reader and try again.");
                }

                return await tcs.Task.ConfigureAwait(false);
            }
            finally
            {
                cancellationRegistration.Dispose();
                // The SDK sample closes the reader with Dispose, which also stops acquisition.
                // Calling CancelCapture from a worker thread first can stall cleanup.
                SafeDisposeReaderOnUiThread(reader, callback);
            }
        }

        private void EnsureReady(Reader reader)
        {
            var statusResult = reader.GetStatus();
            if (statusResult != Constants.ResultCode.DP_SUCCESS)
            {
                throw new InvalidOperationException("Reader status failed: " + statusResult);
            }

            if (reader.Status.Status == Constants.ReaderStatuses.DP_STATUS_NEED_CALIBRATION)
            {
                reader.Calibrate();
            }
            else if (reader.Status.Status == Constants.ReaderStatuses.DP_STATUS_BUSY)
            {
                Thread.Sleep(50);
            }
            else if (reader.Status.Status != Constants.ReaderStatuses.DP_STATUS_READY)
            {
                throw new InvalidOperationException("Reader is not ready: " + reader.Status.Status);
            }
        }

        private bool CheckCaptureResult(CaptureResult captureResult)
        {
            if (captureResult.Data == null)
            {
                if (captureResult.ResultCode != Constants.ResultCode.DP_SUCCESS)
                {
                    throw new InvalidOperationException(captureResult.ResultCode.ToString());
                }

                if (captureResult.Quality != Constants.CaptureQuality.DP_QUALITY_CANCELED)
                {
                    throw new InvalidOperationException("Quality - " + captureResult.Quality);
                }

                return false;
            }

            if (captureResult.ResultCode != Constants.ResultCode.DP_SUCCESS)
            {
                throw new InvalidOperationException(captureResult.ResultCode.ToString());
            }

            return true;
        }

        private Bitmap? CreateBitmapFromCapture(CaptureResult captureResult)
        {
            if (captureResult.Data == null || captureResult.Data.Views.Count == 0)
            {
                return null;
            }

            var view = captureResult.Data.Views[0];
            return CreateBitmap(view.RawImage, view.Width, view.Height);
        }

        private string CreateFingerprintTemplateXml(CaptureResult captureResult)
        {
            if (captureResult.Data == null)
            {
                throw new InvalidOperationException("Fingerprint capture returned no data.");
            }

            var featureResult = FeatureExtraction.CreateFmdFromFid(captureResult.Data, Constants.Formats.Fmd.ANSI);
            if (featureResult.ResultCode != Constants.ResultCode.DP_SUCCESS || featureResult.Data == null)
            {
                throw new InvalidOperationException("Unable to extract fingerprint template: " + featureResult.ResultCode);
            }

            return Fmd.SerializeXml(featureResult.Data);
        }

        public static Fmd DeserializeFingerprintTemplateXml(string templateXml)
        {
            return Fmd.DeserializeXml(templateXml);
        }

        private Bitmap CreateBitmap(byte[] bytes, int width, int height)
        {
            var rgbBytes = new byte[bytes.Length * 3];

            for (int i = 0; i < bytes.Length; i++)
            {
                rgbBytes[i * 3] = bytes[i];
                rgbBytes[i * 3 + 1] = bytes[i];
                rgbBytes[i * 3 + 2] = bytes[i];
            }

            var bitmap = new Bitmap(width, height, PixelFormat.Format24bppRgb);
            var data = bitmap.LockBits(new Rectangle(0, 0, bitmap.Width, bitmap.Height), ImageLockMode.WriteOnly, PixelFormat.Format24bppRgb);

            try
            {
                for (int y = 0; y < bitmap.Height; y++)
                {
                    IntPtr dest = new IntPtr(data.Scan0.ToInt64() + data.Stride * y);
                    System.Runtime.InteropServices.Marshal.Copy(rgbBytes, y * bitmap.Width * 3, dest, bitmap.Width * 3);
                }
            }
            finally
            {
                bitmap.UnlockBits(data);
            }

            return bitmap;
        }

        private Reader StartCaptureOnUiThread(Reader reader, Reader.CaptureCallback callback)
        {
            Reader activeReader = reader;

            InvokeOnUiThread(() =>
            {
                LogCaptureStage("Opening reader.");
                activeReader = OpenReaderWithRecovery(reader);

                activeReader.On_Captured += callback;

                EnsureReady(activeReader);
                LogCaptureStage("Reader ready; starting acquisition.");

                var captureStatus = activeReader.CaptureAsync(
                    Constants.Formats.Fid.ANSI,
                    Constants.CaptureProcessing.DP_IMG_PROC_DEFAULT,
                    activeReader.Capabilities.Resolutions[0]);
                LogCaptureStage("Acquisition start result: " + captureStatus);

                if (captureStatus != Constants.ResultCode.DP_SUCCESS)
                {
                    throw new InvalidOperationException("Unable to start capture: " + captureStatus);
                }
            });

            return activeReader;
        }

        private Reader OpenReaderWithRecovery(Reader reader)
        {
            string serial = reader.Description.SerialNumber;
            var openResult = reader.Open(Constants.CapturePriority.DP_PRIORITY_COOPERATIVE);
            if (openResult == Constants.ResultCode.DP_SUCCESS)
            {
                return reader;
            }

            var attempts = new List<string> { "cooperative=" + openResult };

            if (openResult == Constants.ResultCode.DP_DEVICE_BUSY || openResult == Constants.ResultCode.DP_DEVICE_FAILURE)
            {
                try
                {
                    // Ensure the reader is closed before attempting reset/reopen.
                    TryCancelCapture(reader);
                }
                catch
                {
                    // Ignore close errors — we'll attempt reset/reopen anyway.
                }

                TryResetReader(reader);
                SafeDisposeReader(reader);
                Thread.Sleep(500);

                reader = GetFreshReader(serial) ?? reader;
                openResult = reader.Open(Constants.CapturePriority.DP_PRIORITY_EXCLUSIVE);
                attempts.Add("exclusive=" + openResult);
                if (openResult == Constants.ResultCode.DP_SUCCESS)
                {
                    return reader;
                }

                if (openResult == Constants.ResultCode.DP_DEVICE_BUSY || openResult == Constants.ResultCode.DP_DEVICE_FAILURE)
                {
                    TryResetReader(reader);
                    SafeDisposeReader(reader);
                    Thread.Sleep(500);

                    reader = GetFreshReader(serial) ?? reader;
                    openResult = reader.Open(Constants.CapturePriority.DP_PRIORITY_COOPERATIVE);
                    attempts.Add("cooperative_retry=" + openResult);
                    if (openResult == Constants.ResultCode.DP_SUCCESS)
                    {
                        return reader;
                    }
                }
            }

            throw new InvalidOperationException(BuildOpenReaderFailureMessage(openResult, attempts));
        }

        private Reader? GetFreshReader(string serial)
        {
            try
            {
                var readers = ReaderCollection.GetReaders();
                return readers
                    .Cast<Reader>()
                    .FirstOrDefault(candidate => candidate.Description.SerialNumber == serial)
                    ?? readers.Cast<Reader>().FirstOrDefault();
            }
            catch
            {
                return null;
            }
        }

        private string BuildOpenReaderFailureMessage(Constants.ResultCode resultCode, IReadOnlyCollection<string> attempts)
        {
            string message = "Unable to open reader: " + resultCode + " (" + string.Join(", ", attempts) + ").";

            if (resultCode == Constants.ResultCode.DP_DEVICE_FAILURE || resultCode == Constants.ResultCode.DP_DEVICE_BUSY)
            {
                message += " Unplug and reconnect the scanner, close other fingerprint apps, then restart FingerprintBridge.";
            }

            return message;
        }

        private void SafeDisposeReader(Reader reader)
        {
            try
            {
                reader.Dispose();
            }
            catch
            {
                // Ignore cleanup errors.
            }
        }

        private void TryCancelCapture(Reader reader)
        {
            try
            {
                reader.CancelCapture();
            }
            catch
            {
                // Ignore cancellation errors and continue cleanup.
            }
        }

        private void TryResetReader(Reader reader)
        {
            try
            {
                var resetResult = reader.Reset();
                if (resetResult != Constants.ResultCode.DP_SUCCESS)
                {
                    // Reset is a best-effort recovery step before the retry path.
                }
            }
            catch
            {
                // Ignore reset failures and let the retry path report the real error.
            }
        }

        private void SafeDisposeReaderOnUiThread(Reader reader, Reader.CaptureCallback? callback)
        {
            try
            {
                InvokeOnUiThread(() =>
                {
                    LogCaptureStage("Closing reader.");
                    if (callback != null)
                    {
                        reader.On_Captured -= callback;
                    }

                    reader.Dispose();
                    LogCaptureStage("Reader closed.");
                });
            }
            catch
            {
                // Ignore cleanup errors.
            }
        }

        private static void LogCaptureStage(string message)
        {
            try
            {
                // Operational diagnostics only: never log fingerprint images or templates.
                File.AppendAllText(Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "capture-diagnostic.log"),
                    DateTime.UtcNow.ToString("o") + " " + message + Environment.NewLine);
            }
            catch
            {
                // Diagnostics must not interrupt capture.
            }
        }

        private void InvokeOnUiThread(Action action)
        {
            if (!_uiInvoker.InvokeRequired)
            {
                action();
                return;
            }

            Exception? captured = null;
            var done = new ManualResetEventSlim(false);

            _uiInvoker.BeginInvoke(new Action(() =>
            {
                try
                {
                    action();
                }
                catch (Exception ex)
                {
                    captured = ex;
                }
                finally
                {
                    done.Set();
                }
            }), Array.Empty<object>());

            done.Wait();

            if (captured != null)
            {
                throw captured;
            }
        }

        private Task<T> InvokeOnUiThreadAsync<T>(Func<T> func)
        {
            if (!_uiInvoker.InvokeRequired)
            {
                return Task.FromResult(func());
            }

            var tcs = new TaskCompletionSource<T>(TaskCreationOptions.RunContinuationsAsynchronously);

            _uiInvoker.BeginInvoke(new Action(() =>
            {
                try
                {
                    tcs.TrySetResult(func());
                }
                catch (Exception ex)
                {
                    tcs.TrySetException(ex);
                }
            }), Array.Empty<object>());

            return tcs.Task;
        }
    }
}
