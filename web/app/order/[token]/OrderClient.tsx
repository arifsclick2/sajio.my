"use client";

/* Customer QR ordering client (§15) — a guest scans the QR on the table and
   orders straight to the kitchen. No login, no dashboard chrome, mobile-first. */

import { useEffect, useMemo, useState } from "react";
import { api, PublicMenuResponse } from "../../../lib/api";

interface CartLine {
  product: { id: number; name: string; price: string; image_url?: string | null };
  qty: number;
}

function rm(value: string | number): string {
  return `RM${(typeof value === "number" ? value : Number(value ?? 0)).toFixed(2)}`;
}

type View = "menu" | "cart" | "success";

export default function OrderClient({ token }: { token: string }) {
  const [menu, setMenu] = useState<PublicMenuResponse | null>(null);
  const [loadErr, setLoadErr] = useState<string | null>(null);
  const [activeCat, setActiveCat] = useState<number | "all">("all");
  const [cart, setCart] = useState<Record<number, CartLine>>({});
  const [view, setView] = useState<View>("menu");
  const [customerName, setCustomerName] = useState("");
  const [customerPhone, setCustomerPhone] = useState("");
  const [placing, setPlacing] = useState(false);
  const [placeErr, setPlaceErr] = useState<string | null>(null);
  const [done, setDone] = useState<{ orderNo: string; message: string } | null>(null);

  useEffect(() => {
    let alive = true;
    api
      .publicMenu(token)
      .then((m) => {
        if (alive) {
          setMenu(m);
          setLoadErr(null);
        }
      })
      .catch((e) => {
        if (alive) setLoadErr(e instanceof Error ? e.message : "Could not load the menu.");
      });
    return () => {
      alive = false;
    };
  }, [token]);

  const allProducts = useMemo(
    () => (menu?.categories ?? []).flatMap((c) => c.products.map((p) => ({ ...p, cat: c.name }))),
    [menu],
  );

  const visible = useMemo(() => {
    if (activeCat === "all") return allProducts;
    const cat = (menu?.categories ?? []).find((c) => c.id === activeCat);
    if (!cat) return [];
    return cat.products.map((p) => ({ ...p, cat: cat.name }));
  }, [activeCat, allProducts, menu]);

  const cartLines = useMemo(() => Object.values(cart), [cart]);
  const cartCount = cartLines.reduce((n, l) => n + l.qty, 0);
  const cartTotal = cartLines.reduce((n, l) => n + l.qty * Number(l.product.price), 0);

  function add(p: { id: number; name: string; price: string; image_url?: string | null }) {
    setCart((c) => {
      const line = c[p.id];
      return { ...c, [p.id]: line ? { ...line, qty: line.qty + 1 } : { product: p, qty: 1 } };
    });
  }

  function dec(p: { id: number }) {
    setCart((c) => {
      const line = c[p.id];
      if (!line) return c;
      if (line.qty <= 1) {
        const next = { ...c };
        delete next[p.id];
        return next;
      }
      return { ...c, [p.id]: { ...line, qty: line.qty - 1 } };
    });
  }

  async function placeOrder() {
    if (!token || cartLines.length === 0) return;
    setPlacing(true);
    setPlaceErr(null);
    try {
      const r = await api.publicPlaceOrder(token, {
        items: cartLines.map((l) => ({ product_id: l.product.id, quantity: l.qty })),
        customer_name: customerName.trim() || undefined,
        customer_phone: customerPhone.trim() || undefined,
      });
      setDone({ orderNo: r.order.order_no, message: r.message });
      setCart({});
      setView("success");
    } catch (e) {
      setPlaceErr(e instanceof Error ? e.message : "Could not place the order.");
    } finally {
      setPlacing(false);
    }
  }

  const accent =
    menu?.restaurant.brand_color && menu.restaurant.brand_color !== "#0d9488"
      ? menu.restaurant.brand_color
      : "#e82d4b";

  if (loadErr) {
    return (
      <main className="grid min-h-dvh place-items-center bg-[#fdf8f6] p-6">
        <div className="max-w-sm rounded-2xl border border-stone-200 bg-white p-8 text-center shadow-sm">
          <p className="text-4xl">🤷</p>
          <h1 className="mt-2 text-xl font-black text-ink">Menu unavailable</h1>
          <p className="mt-2 text-sm text-stone-500">{loadErr}</p>
          <p className="mt-2 text-xs text-stone-400">Please ask a staff member for help.</p>
        </div>
      </main>
    );
  }

  if (!menu) {
    return (
      <main className="grid min-h-dvh place-items-center bg-[#fdf8f6] p-6">
        <div className="text-center">
          <div className="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-rasa-200 border-t-rasa-500" />
          <p className="mt-4 text-sm font-semibold text-stone-500">Loading menu…</p>
        </div>
      </main>
    );
  }

  if (view === "success" && done) {
    return (
      <main className="grid min-h-dvh place-items-center bg-[#fdf8f6] p-6">
        <div className="w-full max-w-sm rounded-3xl border border-stone-200 bg-white p-8 text-center shadow-lg">
          <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl">✅</span>
          <h1 className="mt-4 text-2xl font-black text-ink">Order sent!</h1>
          <p className="mt-1 text-sm text-stone-500">Your order has reached the kitchen.</p>
          <p className="mt-5 text-4xl font-black tracking-tight" style={{ color: accent }}>
            {done.orderNo}
          </p>
          <p className="mt-2 text-xs text-stone-400">{done.message}</p>
          <button
            onClick={() => {
              setDone(null);
              setView("menu");
            }}
            className="mt-6 w-full rounded-2xl py-3 text-sm font-black text-white transition"
            style={{ backgroundColor: accent }}
          >
            Order more
          </button>
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-dvh bg-[#fdf8f6] pb-32">
      {/* Restaurant header */}
      <header className="sticky top-0 z-30 border-b border-stone-200/70 bg-[#fdf8f6]/90 backdrop-blur">
        <div className="mx-auto flex max-w-lg items-center gap-3 px-4 py-3">
          {menu.restaurant.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={menu.restaurant.logo_url} alt="" className="h-10 w-10 rounded-xl object-cover ring-1 ring-stone-200" />
          ) : (
            <span
              className="grid h-10 w-10 place-items-center rounded-xl text-lg font-black text-white"
              style={{ backgroundColor: accent }}
            >
              {(menu.restaurant.name || "S").charAt(0).toUpperCase()}
            </span>
          )}
          <div className="min-w-0">
            <h1 className="truncate text-lg font-black tracking-tight text-ink">{menu.restaurant.name}</h1>
            <p className="text-xs font-semibold text-stone-500">Table {menu.table.number} · Order at the counter</p>
          </div>
          {cartCount > 0 && view === "menu" && (
            <button
              onClick={() => setView("cart")}
              className="ml-auto rounded-full px-4 py-2 text-sm font-black text-white shadow-md"
              style={{ backgroundColor: accent }}
            >
              🛒 {cartCount}
            </button>
          )}
        </div>
      </header>

      <div className="mx-auto max-w-lg px-4">
        {/* Category chips */}
        {menu.categories.length > 1 && (
          <div className="scrollbar-none -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 pt-4">
            <button
              onClick={() => setActiveCat("all")}
              className={`shrink-0 rounded-full px-4 py-2 text-sm font-black transition ${
                activeCat === "all" ? "text-white shadow-md" : "border border-stone-200 bg-white text-stone-600"
              }`}
              style={activeCat === "all" ? { backgroundColor: accent } : undefined}
            >
              All
            </button>
            {menu.categories.map((c) => (
              <button
                key={c.id}
                onClick={() => setActiveCat(c.id)}
                className={`shrink-0 rounded-full px-4 py-2 text-sm font-black transition ${
                  activeCat === c.id ? "text-white shadow-md" : "border border-stone-200 bg-white text-stone-600"
                }`}
                style={activeCat === c.id ? { backgroundColor: accent } : undefined}
              >
                {c.name}
              </button>
            ))}
          </div>
        )}

        {/* Product list */}
        <div className="space-y-3 py-4">
          {visible.length === 0 && <p className="py-10 text-center text-sm text-stone-400">Nothing here yet.</p>}
          {visible.map((p) => {
            const qty = cart[p.id]?.qty ?? 0;
            return (
              <div key={p.id} className="flex gap-3 rounded-2xl border border-stone-200 bg-white p-3 shadow-sm">
                {p.image_url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={p.image_url}
                    alt={p.name}
                    className="h-20 w-20 shrink-0 rounded-xl object-cover ring-1 ring-stone-100"
                    onError={(e) => ((e.target as HTMLImageElement).style.display = "none")}
                  />
                ) : (
                  <span className="grid h-20 w-20 shrink-0 place-items-center rounded-xl bg-rasa-50 text-2xl">🍽️</span>
                )}
                <div className="flex min-w-0 flex-1 flex-col justify-between py-0.5">
                  <div>
                    <p className="text-sm font-black leading-tight text-ink">{p.name}</p>
                    {p.description && (
                      <p className="mt-0.5 line-clamp-2 text-xs leading-snug text-stone-500">{p.description}</p>
                    )}
                  </div>
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-black" style={{ color: accent }}>
                      {rm(p.price)}
                    </p>
                    {qty === 0 ? (
                      <button
                        onClick={() => add(p)}
                        className="rounded-full px-4 py-1.5 text-xs font-black text-white shadow-sm transition active:scale-95"
                        style={{ backgroundColor: accent }}
                      >
                        Add +
                      </button>
                    ) : (
                      <div className="flex items-center gap-2 rounded-full px-1 py-0.5 text-white" style={{ backgroundColor: accent }}>
                        <button onClick={() => dec(p)} className="grid h-7 w-7 place-items-center rounded-full text-base font-black active:scale-90">
                          −
                        </button>
                        <span className="min-w-5 text-center text-sm font-black">{qty}</span>
                        <button onClick={() => add(p)} className="grid h-7 w-7 place-items-center rounded-full text-base font-black active:scale-90">
                          +
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Cart bar */}
      {view === "menu" && cartCount > 0 && (
        <div className="fixed inset-x-0 bottom-0 z-40 p-4">
          <button
            onClick={() => setView("cart")}
            className="mx-auto flex w-full max-w-lg items-center justify-between rounded-2xl px-5 py-4 text-white shadow-2xl transition active:scale-[0.99]"
            style={{ backgroundColor: accent }}
          >
            <span className="text-sm font-black">
              🛒 {cartCount} item{cartCount > 1 ? "s" : ""}
            </span>
            <span className="text-base font-black">{rm(cartTotal)}</span>
          </button>
        </div>
      )}

      {/* Cart view */}
      {view === "cart" && (
        <div className="fixed inset-0 z-50 flex flex-col bg-white">
          <div className="flex items-center justify-between border-b border-stone-200 px-4 py-3">
            <button onClick={() => setView("menu")} className="text-sm font-black text-stone-600">
              ← Back to menu
            </button>
            <p className="text-sm font-black text-ink">Your order</p>
            <span className="w-16" />
          </div>

          <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
            {cartLines.length === 0 && <p className="py-10 text-center text-sm text-stone-400">Your cart is empty.</p>}
            {cartLines.map((l) => (
              <div key={l.product.id} className="flex items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-[#fdf8f6] p-3">
                <div className="min-w-0">
                  <p className="truncate text-sm font-black text-ink">{l.product.name}</p>
                  <p className="text-xs font-bold text-stone-500">{rm(Number(l.product.price) * l.qty)}</p>
                </div>
                <div className="flex items-center gap-2 rounded-full bg-white px-1 py-0.5 ring-1 ring-stone-200">
                  <button onClick={() => dec(l.product)} className="grid h-7 w-7 place-items-center rounded-full text-base font-black text-stone-600 active:scale-90">
                    −
                  </button>
                  <span className="min-w-5 text-center text-sm font-black text-ink">{l.qty}</span>
                  <button onClick={() => add(l.product)} className="grid h-7 w-7 place-items-center rounded-full text-base font-black active:scale-90" style={{ color: accent }}>
                    +
                  </button>
                </div>
              </div>
            ))}

            {cartLines.length > 0 && (
              <div className="rounded-2xl border border-stone-200 bg-white p-4">
                <p className="text-xs font-black uppercase tracking-wide text-stone-500">Who&apos;s ordering? (optional)</p>
                <input
                  value={customerName}
                  onChange={(e) => setCustomerName(e.target.value)}
                  placeholder="Your name"
                  className="mt-2 w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm font-semibold outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100"
                />
                <input
                  value={customerPhone}
                  onChange={(e) => setCustomerPhone(e.target.value)}
                  placeholder="Phone (for updates)"
                  inputMode="tel"
                  className="mt-2 w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm font-semibold outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100"
                />
              </div>
            )}
          </div>

          {cartLines.length > 0 && (
            <div className="border-t border-stone-200 bg-white px-4 py-4">
              {placeErr && <p className="mb-2 rounded-xl bg-red-50 px-3 py-2 text-sm font-semibold text-red-600">{placeErr}</p>}
              <div className="flex items-center justify-between text-sm font-bold text-stone-600">
                <span>Total</span>
                <span className="text-xl font-black text-ink">{rm(cartTotal)}</span>
              </div>
              <button
                onClick={placeOrder}
                disabled={placing}
                className="mt-3 w-full rounded-2xl py-3.5 text-base font-black text-white shadow-lg transition active:scale-[0.99] disabled:opacity-60"
                style={{ backgroundColor: accent }}
              >
                {placing ? "Sending order…" : "Send order to kitchen"}
              </button>
              <p className="mt-2 text-center text-[11px] font-semibold text-stone-400">
                Pay at the counter when you&apos;re done 🧾
              </p>
            </div>
          )}
        </div>
      )}
    </main>
  );
}
