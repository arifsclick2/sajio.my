import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Standalone build so we can run `node server.js` under PM2 without the full node_modules.
  output: "standalone",
};

export default nextConfig;
