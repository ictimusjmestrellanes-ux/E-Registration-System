using System;
using System.IO;
using System.Net;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.ComponentModel;
using System.Collections.Generic;
using DPUruNet;
using System.Web.Script.Serialization;
using System.Drawing;
using System.Drawing.Imaging;
using System.Runtime.InteropServices;

namespace FingerprintBridge
{
    public sealed class FingerprintBridgeServer
    {
        private const string Prefix = "http://127.0.0.1:38654/";
        private readonly ISynchronizeInvoke _uiInvoker;
        private readonly HttpListener _listener = new HttpListener();
        private readonly JavaScriptSerializer _serializer = new JavaScriptSerializer();
        private readonly FingerprintCaptureService _captureService;
        private CancellationTokenSource? _cts;
        private Task? _loopTask;

        public FingerprintBridgeServer(ISynchronizeInvoke uiInvoker)
        {
            _uiInvoker = uiInvoker;
            _captureService = new FingerprintCaptureService(uiInvoker);
        }

        public void Start()
        {
            if (_listener.IsListening)
            {
                return;
            }

            _cts = new CancellationTokenSource();
            _listener.Prefixes.Add(Prefix);
            _listener.Start();
            _loopTask = Task.Run(() => ListenLoop(_cts.Token));
        }

        public void Stop()
        {
            try
            {
                _cts?.Cancel();
                if (_listener.IsListening)
                {
                    _listener.Stop();
                    _listener.Close();
                }
            }
            catch
            {
                // Best effort shutdown.
            }
        }

        private void ListenLoop(CancellationToken token)
        {
            while (!token.IsCancellationRequested)
            {
                HttpListenerContext? context = null;

                try
                {
                    context = _listener.GetContext();
                }
                catch (HttpListenerException)
                {
                    break;
                }
                catch (ObjectDisposedException)
                {
                    break;
                }

                if (context == null)
                {
                    continue;
                }

                _ = Task.Run(() => ProcessRequestAsync(context, token));
            }
        }

        private async Task ProcessRequestAsync(HttpListenerContext context, CancellationToken token)
        {
            ApplyCors(context.Response);

            if (context.Request.HttpMethod == "OPTIONS")
            {
                context.Response.StatusCode = 204;
                context.Response.Close();
                return;
            }

            try
            {
                string path = context.Request.Url?.AbsolutePath?.TrimEnd('/') ?? string.Empty;

                if (path.Equals("/api/health", StringComparison.OrdinalIgnoreCase))
                {
                    var readers = await _captureService.GetReadersSnapshotAsync().ConfigureAwait(false);
                    await WriteJsonAsync(context.Response, new
                    {
                        success = true,
                        service = "DigitalPersona Fingerprint Bridge",
                        readers = readers
                    }).ConfigureAwait(false);
                    return;
                }

                if (path.Equals("/api/readers", StringComparison.OrdinalIgnoreCase))
                {
                    var readers = await _captureService.GetReadersSnapshotAsync().ConfigureAwait(false);
                    await WriteJsonAsync(context.Response, new
                    {
                        success = true,
                        readers = readers
                    }).ConfigureAwait(false);
                    return;
                }

                if (path.Equals("/api/capture", StringComparison.OrdinalIgnoreCase) && context.Request.HttpMethod == "POST")
                {
                    var result = await _captureService.CaptureAsync(token).ConfigureAwait(false);
                    await WriteJsonAsync(context.Response, result).ConfigureAwait(false);
                    return;
                }

                if (path.Equals("/api/match", StringComparison.OrdinalIgnoreCase) && context.Request.HttpMethod == "POST")
                {
                    var request = await ReadJsonAsync<MatchRequest>(context.Request).ConfigureAwait(false);
                    var result = await MatchFingerprintAsync(request).ConfigureAwait(false);
                    await WriteJsonAsync(context.Response, result).ConfigureAwait(false);
                    return;
                }

                context.Response.StatusCode = 404;
                await WriteJsonAsync(context.Response, new
                {
                    success = false,
                    message = "Not found."
                }).ConfigureAwait(false);
            }
            catch (OperationCanceledException)
            {
                if (!context.Response.OutputStream.CanWrite)
                {
                    return;
                }

                context.Response.StatusCode = 499;
                await WriteJsonAsync(context.Response, new
                {
                    success = false,
                    message = "Capture canceled."
                }).ConfigureAwait(false);
            }
            catch (Exception ex)
            {
                context.Response.StatusCode = 500;
                await WriteJsonAsync(context.Response, new
                {
                    success = false,
                    message = ex.Message
                }).ConfigureAwait(false);
            }
        }

        private Task<T> InvokeOnUiThreadAsync<T>(Func<T> action)
        {
            if (!_uiInvoker.InvokeRequired)
            {
                return Task.FromResult(action());
            }

            var tcs = new TaskCompletionSource<T>(TaskCreationOptions.RunContinuationsAsynchronously);
            _uiInvoker.BeginInvoke(new Action(() =>
            {
                try
                {
                    tcs.TrySetResult(action());
                }
                catch (Exception ex)
                {
                    tcs.TrySetException(ex);
                }
            }), Array.Empty<object>());

            return tcs.Task;
        }

        private async Task<T> ReadJsonAsync<T>(HttpListenerRequest request)
        {
            using (var reader = new StreamReader(request.InputStream, request.ContentEncoding))
            {
                string body = await reader.ReadToEndAsync().ConfigureAwait(false);
                return _serializer.Deserialize<T>(body);
            }
        }

        private async Task<object> MatchFingerprintAsync(MatchRequest request)
        {
            if (request == null)
            {
                throw new InvalidOperationException("Missing candidate fingerprint template.");
            }

            if (string.IsNullOrWhiteSpace(request.fingerprintTemplateXml) && string.IsNullOrWhiteSpace(request.fingerprintImageDataUrl))
            {
                throw new InvalidOperationException("Missing candidate fingerprint template.");
            }

            if (request.clients == null || request.clients.Count == 0)
            {
                return new
                {
                    success = true,
                    matched = false,
                    message = "No fingerprint templates were provided.",
                    bestScore = (int?)null
                };
            }

            var candidate = !string.IsNullOrWhiteSpace(request.fingerprintTemplateXml)
                ? FingerprintCaptureService.DeserializeFingerprintTemplateXml(request.fingerprintTemplateXml)
                : ExtractFingerprintTemplateFromImageDataUrl(request.fingerprintImageDataUrl);
            const int probabilityOne = 0x7fffffff;
            int thresholdScore = probabilityOne / 100000;

            MatchCandidate? bestMatch = null;
            int bestScore = int.MaxValue;

            foreach (var client in request.clients)
            {
                if (client == null)
                {
                    continue;
                }

                var fingerprintTemplateXml = client.fingerprintTemplateXml;
                var fingerprintImageDataUrl = client.fingerprintImageDataUrl;

                if (string.IsNullOrWhiteSpace(fingerprintTemplateXml) && string.IsNullOrWhiteSpace(fingerprintImageDataUrl))
                {
                    continue;
                }

                Fmd target;
                try
                {
                    target = !string.IsNullOrWhiteSpace(fingerprintTemplateXml)
                        ? FingerprintCaptureService.DeserializeFingerprintTemplateXml(fingerprintTemplateXml)
                        : ExtractFingerprintTemplateFromImageDataUrl(fingerprintImageDataUrl);
                }
                catch
                {
                    continue;
                }

                var compareResult = Comparison.Compare(candidate, 0, target, 0);
                if (compareResult.ResultCode != Constants.ResultCode.DP_SUCCESS)
                {
                    continue;
                }

                if (compareResult.Score < bestScore)
                {
                    bestScore = compareResult.Score;
                    bestMatch = client;
                }
            }

            if (bestMatch != null && bestScore < thresholdScore)
            {
                return new
                {
                    success = true,
                    matched = true,
                    matchedClientId = bestMatch.id,
                    matchedClientName = bestMatch.name,
                    bestScore = bestScore,
                    thresholdScore = thresholdScore
                };
            }

            return new
            {
                success = true,
                matched = false,
                matchedClientId = (int?)null,
                matchedClientName = string.Empty,
                bestScore = bestScore == int.MaxValue ? (int?)null : bestScore,
                thresholdScore = thresholdScore,
                message = "No matching client fingerprint was found."
            };
        }

        private void ApplyCors(HttpListenerResponse response)
        {
            response.AddHeader("Access-Control-Allow-Origin", "*");
            response.AddHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
            response.AddHeader("Access-Control-Allow-Headers", "Content-Type");
        }

        private async Task WriteJsonAsync(HttpListenerResponse response, object payload)
        {
            response.ContentType = "application/json; charset=utf-8";
            string json = _serializer.Serialize(payload);
            byte[] buffer = Encoding.UTF8.GetBytes(json);
            response.ContentLength64 = buffer.Length;

            using (var output = response.OutputStream)
            {
                await output.WriteAsync(buffer, 0, buffer.Length).ConfigureAwait(false);
            }
        }

        private Fmd ExtractFingerprintTemplateFromImageDataUrl(string imageDataUrl)
        {
            if (string.IsNullOrWhiteSpace(imageDataUrl))
            {
                throw new InvalidOperationException("Missing fingerprint image data.");
            }

            string base64 = imageDataUrl;
            int commaIndex = imageDataUrl.IndexOf(',');
            if (commaIndex >= 0)
            {
                base64 = imageDataUrl.Substring(commaIndex + 1);
            }

            byte[] imageBytes = Convert.FromBase64String(base64);

            using (var stream = new MemoryStream(imageBytes))
            using (var bitmap = new Bitmap(stream))
            {
                byte[] rawImage = GetRawGrayscaleBytes(bitmap);
                var result = FeatureExtraction.CreateFmdFromRaw(
                    rawImage,
                    0,
                    0,
                    bitmap.Width,
                    bitmap.Height,
                    500,
                    Constants.Formats.Fmd.ANSI);

                if (result.ResultCode != Constants.ResultCode.DP_SUCCESS || result.Data == null)
                {
                    throw new InvalidOperationException("Unable to extract fingerprint template from image: " + result.ResultCode);
                }

                return result.Data;
            }
        }

        private byte[] GetRawGrayscaleBytes(Bitmap bitmap)
        {
            var rect = new Rectangle(0, 0, bitmap.Width, bitmap.Height);
            var data = bitmap.LockBits(rect, ImageLockMode.ReadOnly, PixelFormat.Format24bppRgb);

            try
            {
                int stride = Math.Abs(data.Stride);
                byte[] buffer = new byte[stride * bitmap.Height];
                Marshal.Copy(data.Scan0, buffer, 0, buffer.Length);

                byte[] raw = new byte[bitmap.Width * bitmap.Height];
                for (int y = 0; y < bitmap.Height; y++)
                {
                    int sourceRow = y * stride;
                    int destinationRow = y * bitmap.Width;

                    for (int x = 0; x < bitmap.Width; x++)
                    {
                        int sourceIndex = sourceRow + (x * 3);
                        raw[destinationRow + x] = buffer[sourceIndex];
                    }
                }

                return raw;
            }
            finally
            {
                bitmap.UnlockBits(data);
            }
        }

        public sealed class MatchRequest
        {
            public string fingerprintTemplateXml { get; set; } = string.Empty;
            public string fingerprintImageDataUrl { get; set; } = string.Empty;
            public List<MatchCandidate> clients { get; set; } = new List<MatchCandidate>();
        }

        public sealed class MatchCandidate
        {
            public int id { get; set; }
            public string name { get; set; } = string.Empty;
            public string fingerprintTemplateXml { get; set; } = string.Empty;
            public string fingerprintImageDataUrl { get; set; } = string.Empty;
        }
    }
}
