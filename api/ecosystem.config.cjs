/**
 * PM2 processes for the Sajio Laravel API (background workers).
 *
 * Start / restart:
 *   pm2 startOrRestart ecosystem.config.cjs
 *
 * The queue worker runs as www-data (same as PHP-FPM) so log files stay
 * group-consistent and web + queue can share storage/logs.
 */
module.exports = {
  apps: [
    {
      name: "sajio-queue",
      cwd: "/var/www/sajio.my/api",
      script: "/usr/bin/php",
      args: "artisan queue:work redis --sleep=3 --tries=3 --max-time=3600",
      instances: 1,
      exec_mode: "fork",
      uid: "www-data",
      gid: "www-data",
      env: {
        NODE_ENV: "production",
      },
      out_file: "/var/log/sajio-queue.out.log",
      error_file: "/var/log/sajio-queue.err.log",
      merge_logs: true,
      time: true,
      max_restarts: 10,
      autorestart: true,
      // Restart the worker hourly so long-lived memory stays healthy.
      cron_restart: "0 * * * *",
    },
  ],
};
