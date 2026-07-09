using System;
using System.IO;
using System.Windows.Forms;

namespace FingerprintBridge
{
    public sealed class BridgeForm : Form
    {
        private readonly FingerprintBridgeServer _server;

        public BridgeForm()
        {
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
                File.AppendAllText(GetLogPath(), DateTime.Now.ToString("s") + " Bridge started." + Environment.NewLine);
            }
            catch (Exception ex)
            {
                File.AppendAllText(GetLogPath(), DateTime.Now.ToString("s") + " Bridge failed to start: " + ex + Environment.NewLine);
                MessageBox.Show(
                    "Fingerprint bridge failed to start.\n\n" + ex.Message,
                    "Fingerprint Bridge",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error);
                Close();
            }
        }

        private void OnFormClosing(object? sender, FormClosingEventArgs e)
        {
            _server.Stop();
        }

        private static string GetLogPath()
        {
            return Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "FingerprintBridge.log");
        }
    }
}
