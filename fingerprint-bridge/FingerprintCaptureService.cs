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
            var readers = await InvokeOnUiThreadAsync(() => ReaderCollection.GetReaders()).ConfigureAwait(false);
            if (readers.Count == 0)
            {
                throw new InvalidOperationException("No DigitalPersona readers were detected.");
            }

            var reader = readers[0];
            var captureResult = await CaptureSingleFingerprintAsync(reader, cancellationToken).ConfigureAwait(false);
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

        private Task<CaptureResult> CaptureSingleFingerprintAsync(Reader reader, CancellationToken cancellationToken)
        {
            var tcs = new TaskCompletionSource<CaptureResult>(TaskCreationOptions.RunContinuationsAsynchronously);
            Reader.CaptureCallback? callback = null;

            cancellationToken.Register(() => tcs.TrySetCanceled(cancellationToken));

            try
            {
                callback = captureResult =>
                {
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

                StartCaptureOnUiThread(reader, callback);
            }
            catch
            {
                SafeDisposeReaderOnUiThread(reader, callback);
                throw;
            }

            return WaitForCaptureAsync(reader, callback, tcs, cancellationToken);
        }

        private async Task<CaptureResult> WaitForCaptureAsync(
            Reader reader,
            Reader.CaptureCallback? callback,
            TaskCompletionSource<CaptureResult> tcs,
            CancellationToken cancellationToken)
        {
            try
            {
                using (var timeoutCts = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken))
                {
                    timeoutCts.CancelAfter(TimeSpan.FromSeconds(45));
                    var completed = await Task.WhenAny(tcs.Task, Task.Delay(Timeout.Infinite, timeoutCts.Token)).ConfigureAwait(false);

                    if (completed != tcs.Task)
                    {
                        throw new TimeoutException("Fingerprint capture timed out.");
                    }

                    return await tcs.Task.ConfigureAwait(false);
                }
            }
            finally
            {
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

        private void StartCaptureOnUiThread(Reader reader, Reader.CaptureCallback callback)
        {
            InvokeOnUiThread(() =>
            {
                var openResult = OpenReaderWithRecovery(reader);
                if (openResult != Constants.ResultCode.DP_SUCCESS)
                {
                    throw new InvalidOperationException("Unable to open reader: " + openResult);
                }

                reader.On_Captured += callback;

                EnsureReady(reader);

                var captureStatus = reader.CaptureAsync(
                    Constants.Formats.Fid.ANSI,
                    Constants.CaptureProcessing.DP_IMG_PROC_DEFAULT,
                    reader.Capabilities.Resolutions[0]);

                if (captureStatus != Constants.ResultCode.DP_SUCCESS)
                {
                    throw new InvalidOperationException("Unable to start capture: " + captureStatus);
                }
            });
        }

        private Constants.ResultCode OpenReaderWithRecovery(Reader reader)
        {
            var openResult = reader.Open(Constants.CapturePriority.DP_PRIORITY_EXCLUSIVE);
            if (openResult == Constants.ResultCode.DP_SUCCESS)
            {
                return openResult;
            }

            if (openResult == Constants.ResultCode.DP_DEVICE_BUSY || openResult == Constants.ResultCode.DP_DEVICE_FAILURE)
            {
                try
                {
                    // Ensure the reader is closed before attempting reset/reopen.
                    reader.Dispose();
                }
                catch
                {
                    // Ignore close errors — we'll attempt reset/reopen anyway.
                }

                TryResetReader(reader);
                Thread.Sleep(250);

                openResult = reader.Open(Constants.CapturePriority.DP_PRIORITY_EXCLUSIVE);
                if (openResult == Constants.ResultCode.DP_SUCCESS)
                {
                    return openResult;
                }

                if (openResult == Constants.ResultCode.DP_DEVICE_BUSY || openResult == Constants.ResultCode.DP_DEVICE_FAILURE)
                {
                    try
                    {
                        reader.Dispose();
                    }
                    catch
                    {
                        // ignore
                    }

                    Thread.Sleep(250);
                    openResult = reader.Open(Constants.CapturePriority.DP_PRIORITY_COOPERATIVE);
                }
            }

            return openResult;
        }

        private void TryResetReader(Reader reader)
        {
            try
            {
                try
                {
                    reader.Dispose();
                }
                catch
                {
                    // Ignore close errors - reset may still help.
                }

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
                    if (callback != null)
                    {
                        reader.On_Captured -= callback;
                    }

                    reader.Dispose();
                });
            }
            catch
            {
                // Ignore cleanup errors.
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
