using System;
using System.Threading;
using System.Windows.Forms;

namespace FingerprintBridge
{
    internal static class Program
    {
        private const string MutexName = "Global\\FingerprintBridge_ERegSystem";

        [STAThread]
        private static void Main()
        {
            bool createdNew;
            using (var mutex = new Mutex(true, MutexName, out createdNew))
            {
                if (!createdNew)
                {
                    return;
                }

            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new BridgeForm());
            }
        }
    }
}
