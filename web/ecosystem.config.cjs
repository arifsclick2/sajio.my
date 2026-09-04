/**
 * PM2 process config for Sajio web (Next.js standalone).
 *
 * Start / restart:
 *   pm2 startOrRestart ecosystem.config.cjs
 *
 * Requires a production build first:
 *   ./deploy.sh
 */
module.exports = {
  apps: [
    {
      name: "sajio-web",
      cwd: "/var/www/sajio.my/web",
      script: ".next/standalone/server.js",
      instances: 1,
      exec_mode: "fork",
      env: {
        NODE_ENV: "production",
        HOSTNAME: "127.0.0.1",
        // NOTE: 3000 is used by naiumbarbershop.com.my's app (salon-frontend).
        // Sajio web runs on 3100 — nginx proxies sajio.my/app/*/tenants here.
        PORT: 3100,
      },
      out_file: "/var/log/sajio-web.out.log",
      error_file: "/var/log/sajio-web.err.log",
      merge_logs: true,
      time: true,
      max_restarts: 10,
      autorestart: true,
    },
  ],
};
