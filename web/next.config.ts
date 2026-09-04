import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Standalone build so we can run `node server.js` under PM2 without the full node_modules.
  output: "standalone",
  images: {
    // Remote lifestyle photos are served as-is (no sharp dependency needed on the server).
    unoptimized: true,
    remotePatterns: [{ protocol: "https", hostname: "images.unsplash.com" }],
  },
};

export default nextConfig;
