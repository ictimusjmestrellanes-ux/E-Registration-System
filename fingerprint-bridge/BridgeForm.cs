using System;
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
            }
            catch (Exception ex)
            {
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
    }
}
