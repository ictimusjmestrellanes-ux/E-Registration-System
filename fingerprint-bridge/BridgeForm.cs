using System;
using System.Linq;
using System.Windows.Forms;

namespace FingerprintBridge
{
    public sealed class BridgeForm : Form
    {
        private readonly FingerprintBridgeServer _server;
        private readonly bool _silent;
        private Timer? _watchdog;

        public BridgeForm()
        {
            _silent = Environment.GetCommandLineArgs()
                .Any(a => a.Equals("/auto", StringComparison.OrdinalIgnoreCase) ||
                          a.Equals("-auto", StringComparison.OrdinalIgnoreCase));

            _server = new FingerprintBridgeServer(this);

            ShowInTaskbar = false;
            WindowState = FormWindowState.Minimized;
            Opacity = 0;
            Load += OnLoad;
            FormClosing += OnFormClosing;
        }

        private void OnLoad(object? sender, EventArgs e)
        {
            Hide();

            try
            {
                _server.Start();
                StartWatchdog();
            }
            catch (Exception ex)
            {
                if (!_silent)
                {
                    MessageBox.Show(
                        "Fingerprint bridge failed to start.\n\n" + ex.Message,
                        "Fingerprint Bridge",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Error);
                }

                Close();
            }
        }

        private void StartWatchdog()
        {
            _watchdog = new Timer
            {
                Interval = 30000
            };

            _watchdog.Tick += (_, _) =>
            {
                try
                {
                    if (_server.IsHealthy())
                    {
                        return;
                    }

                    _server.Stop();
                    _server.Start();
                }
                catch
                {
                    Application.Exit();
                }
            };

            _watchdog.Start();
        }

        private void OnFormClosing(object? sender, FormClosingEventArgs e)
        {
            _watchdog?.Stop();
            _watchdog?.Dispose();
            _server.Stop();
        }
    }
}
