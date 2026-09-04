import OrderClient from "./OrderClient";

/* Customer QR ordering page (§15) — a guest scans the QR on the table and
   orders straight to the kitchen. No login, no dashboard chrome, mobile-first. */
export default async function OrderPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  return <OrderClient token={token} />;
}
