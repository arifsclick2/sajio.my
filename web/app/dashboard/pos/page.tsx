"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import AppShell, { useDashboard } from "../../../components/dashboard/AppShell";
import { rm, timeOnly } from "../../../components/dashboard/money";
import { printReceipt } from "../../../components/dashboard/printReceipt";
import { api, BillResponse, CategoryInfo, OrderInfo, ProductInfo, SessionInfo, TableInfo } from "../../../lib/api";
import { getToken } from "../../../lib/session";

const STATUS_LABEL: Record<string, string> = {
  new: "New",
  preparing: "Preparing",
  ready: "Ready",
  served: "Served",
  completed: "Completed",
  cancelled: "Cancelled",
  voided: "Void",
};

/** Allowed forward action (first non-cancel) per status — mirrors backend map. */
const FORWARD: Record<string, { next?: string; label: string }> = {
  new: { next: "preparing", label: "→ Preparing" },
  preparing: { next: "ready", label: "→ Ready" },
  ready: { next: "served", label: "→ Served" },
  served: { next: "completed", label: "→ Completed" },
  completed: { label: "Completed ✓" },
  cancelled: { label: "Cancelled" },
  voided: { label: "Void" },
};

const CANCEL_ALLOWED = new Set(["new", "preparing"]);
const PAYABLE = new Set(["new", "preparing", "ready", "served"]);

const METHODS = [
  { m: "cash", label: "💵 Cash" },
  { m: "card", label: "💳 Card" },
  { m: "qr", label: "📱 QR / DuitNow" },
  { m: "other", label: "🔘 Other" },
];

interface CartLine {
  product: ProductInfo;
  qty: number;
}

interface PayDialog {
  order: OrderInfo | null; // set for takeaway (single order pay)
  sessionId: number | null; // set for dine-in (session settle)
  tableNumber: string | null;
  bill: BillResponse | null;
  method: string;
  busy: boolean;
}

function PosPageContent() {
  const { role, attendance, clockIn } = useDashboard();
  const token = getToken();
  const canManage = role === "owner" || role === "manager";
  const staffLabel = role === "manager" ? "Manager" : "Staff";

  const [tab, setTab] = useState<"order" | "floor">("order");
  const [type, setType] = useState<"dine_in" | "takeaway">("dine_in");
  const [tables, setTables] = useState<TableInfo[]>([]);
  const [sessions, setSessions] = useState<SessionInfo[]>([]);
  const [tableSel, setTableSel] = useState<TableInfo | null>(null);
  const [cats, setCats] = useState<CategoryInfo[]>([]);
  const [products, setProducts] = useState<ProductInfo[]>([]);
  const [catSel, setCatSel] = useState<number | null>(null);
  const [cart, setCart] = useState<CartLine[]>([]);
  const [discount, setDiscount] = useState("");
  const [note, setNote] = useState("");
  const [sending, setSending] = useState(false);
  const [orders, setOrders] = useState<OrderInfo[]>([]);
  const [statusFilter, setStatusFilter] = useState("all");
  const [toast, setToast] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [pay, setPay] = useState<PayDialog | null>(null);

  const onDuty = attendance ? attendance.on_duty : true; // owners exempt

  function flash(text: string, isErr = false) {
    if (isErr) {
      setErr(text);
      setTimeout(() => setErr(null), 4000);
    } else {
      setToast(text);
      setTimeout(() => setToast(null), 2500);
    }
  }

  const loadMenu = useCallback(async () => {
    if (!token) return;
    try {
      const [c, p, t, s] = await Promise.all([
        api.categories(token),
        api.products(token, { per_page: 300 }),
        api.tables(token),
        api.openSessions(token),
      ]);
      setCats(c.categories);
      const list = (p as unknown as { data: ProductInfo[] }).data ?? [];
      setProducts(list);
      setTables(t.tables.filter((x) => x.is_active));
      setSessions(s.sessions);
    } catch (e) {
      flash(e instanceof Error ? e.message : "Failed to load menu.", true);
    }
  }, [token]);

  const loadOrders = useCallback(async () => {
    if (!token) return;
    try {
      const r = await api.ordersIndex(token, { per_page: 50 });
      setOrders(r.data);
    } catch {
      /* floor refresh is best-effort */
    }
  }, [token]);

  useEffect(() => {
    // Initial menu/tables/sessions fetch on mount.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadMenu();
  }, [loadMenu]);

  // Poll open orders while on the floor view.
  useEffect(() => {
    if (tab !== "floor") return;
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadOrders();
    const id = setInterval(() => {
      if (document.visibilityState === "visible") void loadOrders();
    }, 12000);
    return () => clearInterval(id);
  }, [tab, loadOrders]);

  const sessionForTable = useMemo(() => {
    const m = new Map<number, SessionInfo>();
    sessions.forEach((s) => s.table && m.set(s.table.id, s));
    return m;
  }, [sessions]);

  const openCount = orders.filter((o) => PAYABLE.has(o.status)).length;

  /* ---------------- cart ---------------- */

  function addToCart(p: ProductInfo) {
    setCart((prev) => {
      const found = prev.find((l) => l.product.id === p.id);
      if (found) return prev.map((l) => (l.product.id === p.id ? { ...l, qty: l.qty + 1 } : l));
      return [...prev, { product: p, qty: 1 }];
    });
  }

  function setQty(productId: number, qty: number) {
    setCart((prev) =>
      qty <= 0 ? prev.filter((l) => l.product.id !== productId) : prev.map((l) => (l.product.id === productId ? { ...l, qty } : l)),
    );
  }

  const subtotal = useMemo(() => cart.reduce((sum, l) => sum + Number(l.product.price) * l.qty, 0), [cart]);
  const discountVal = Math.min(Math.max(Number(discount) || 0, 0), subtotal);
  const total = Math.max(subtotal - discountVal, 0);

  async function sendOrder() {
    if (!token) return;
    if (cart.length === 0) {
      flash("Cart is empty.", true);
      return;
    }
    if (type === "dine_in" && !tableSel) {
      flash("Choose table untuk dine-in.", true);
      return;
    }
    if (!onDuty) {
      flash("Clock in first before taking orders.", true);
      return;
    }
    setSending(true);
    setErr(null);
    try {
      const res = await api.createOrder(token, {
        type,
        table_id: type === "dine_in" ? tableSel!.id : undefined,
        items: cart.map((l) => ({ product_id: l.product.id, quantity: l.qty })),
        discount: discountVal > 0 ? discountVal : undefined,
        note: note.trim() || undefined,
      });
      flash(res.message);
      setCart([]);
      setDiscount("");
      setNote("");
      if (type === "dine_in") setTableSel(null);
      setTab("floor");
      await loadOrders();
      void loadMenu();
    } catch (e) {
      flash(e instanceof Error ? e.message : "Failed to send order.", true);
    } finally {
      setSending(false);
    }
  }

  /* ---------------- floor actions ---------------- */

  async function advance(order: OrderInfo, status: string) {
    if (!token) return;
    try {
      await api.orderStatus(token, order.id, status);
      await loadOrders();
      void loadMenu();
    } catch (e) {
      flash(e instanceof Error ? e.message : "Failed to update status.", true);
    }
  }

  async function openPay(order: OrderInfo) {
    if (!token) return;
    if (order.type === "dine_in" && order.table) {
      setPay({ order: null, sessionId: order.table_session_id ?? null, tableNumber: order.table.number, bill: null, method: "", busy: false });
      try {
        const bill = await api.tableCurrent(token, order.table.id);
        setPay((p) => (p ? { ...p, bill } : p));
      } catch (e) {
        flash(e instanceof Error ? e.message : "Failed to load bill.", true);
      }
    } else {
      setPay({
        order,
        sessionId: null,
        tableNumber: null,
        bill: { table: { id: 0, number: "Takeaway" }, orders: [order], bill_total: order.total },
        method: "",
        busy: false,
      });
    }
  }

  async function confirmPay() {
    const d = pay;
    if (!token || !d || !d.method) return;
    setPay({ ...d, busy: true });
    try {
      if (d.sessionId && d.bill) {
        await api.settleSession(token, d.sessionId, { method: d.method });
        flash("Table bill settled ✅");
        const receipt = await api.sessionReceipt(token, d.sessionId);
        printReceipt(receipt.receipt);
      } else if (d.order) {
        await api.payOrder(token, d.order.id, { method: d.method });
        flash(`${d.order.order_no} paid ✅`);
        const receipt = await api.orderReceipt(token, d.order.id);
        printReceipt(receipt.receipt);
      }
      setPay(null);
      await loadOrders();
      void loadMenu();
    } catch (e) {
      flash(e instanceof Error ? e.message : "Failed to process payment.", true);
      setPay((p) => (p ? { ...p, busy: false } : p));
    }
  }

  /* ---------------- render ---------------- */

  const filtered = statusFilter === "all" ? orders : orders.filter((o) => o.status === statusFilter);
  const shownCats = catSel ? products.filter((p) => p.category_id === catSel) : products;

  return (
    <>
      {/* Header + tabs */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-ink">POS 🧾</h1>
          <p className="text-sm text-stone-500">
            {canManage ? "" : `${staffLabel} · `}
            {attendance ? (onDuty ? "● On duty" : "○ Not clocked in") : "Restaurant owner"}
          </p>
        </div>
        <div className="flex rounded-xl border border-stone-200 bg-white p-1">
          <button onClick={() => setTab("order")} className={`rounded-lg px-4 py-2 text-sm font-black transition ${tab === "order" ? "bg-rasa-600 text-white" : "text-stone-500 hover:text-rasa-600"}`}>
            + Order baru
          </button>
          <button onClick={() => setTab("floor")} className={`rounded-lg px-4 py-2 text-sm font-black transition ${tab === "floor" ? "bg-rasa-600 text-white" : "text-stone-500 hover:text-rasa-600"}`}>
            Floor {openCount > 0 ? `(${openCount})` : ""}
          </button>
        </div>
      </div>

      {toast && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{toast}</div>}
      {err && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">{err}</div>}

      {/* Clock-in nudge */}
      {attendance && !onDuty && (
        <div className="flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
          <p className="text-sm font-semibold text-amber-700">You need to clock in before taking orders.</p>
          <button onClick={() => void clockIn()} className="rounded-lg bg-amber-600 px-4 py-2 text-xs font-black text-white transition hover:bg-amber-500">
            Clock in now
          </button>
        </div>
      )}

      {tab === "order" ? (
        <div className="grid gap-5 xl:grid-cols-[1fr_360px]">
          {/* Left: type + tables + products */}
          <div className="min-w-0 space-y-4">
            <div className="flex gap-2">
              {(
                [
                  ["dine_in", "Dine-in"],
                  ["takeaway", "Takeaway"],
                ] as const
              ).map(([v, label]) => (
                <button
                  key={v}
                  onClick={() => {
                    setType(v);
                    if (v === "takeaway") setTableSel(null);
                  }}
                  className={`rounded-xl px-5 py-2.5 text-sm font-black transition ${
                    type === v ? "bg-rasa-600 text-white shadow-md" : "border border-stone-200 bg-white text-stone-600 hover:border-rasa-300"
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>

            {type === "dine_in" && (
              <div className="rounded-2xl border border-stone-200 bg-white p-4">
                <p className="mb-2 text-xs font-black uppercase tracking-wide text-stone-500">Choose table</p>
                <div className="flex flex-wrap gap-2">
                  {tables.map((t) => {
                    const occupied = sessionForTable.has(t.id);
                    const active = tableSel?.id === t.id;
                    return (
                      <button
                        key={t.id}
                        onClick={() => setTableSel(active ? null : t)}
                        className={`rounded-xl border-2 px-4 py-2 text-sm font-black transition ${
                          active
                            ? "border-rasa-600 bg-rasa-600 text-white"
                            : occupied
                              ? "border-rasa-300 bg-rasa-50 text-rasa-700"
                              : "border-stone-200 bg-white text-stone-700 hover:border-rasa-300"
                        }`}
                      >
                        {t.number}
                        {occupied && <span className="ml-1 text-[9px] font-bold opacity-70">●</span>}
                      </button>
                    );
                  })}
                  {tables.length === 0 && <p className="text-sm text-stone-400">No tables yet — create some on the Tables page first.</p>}
                </div>
                {tableSel && (
                  <p className="mt-2 text-xs font-bold text-rasa-600">
                    Table {tableSel.number} selected{sessionForTable.has(tableSel.id) ? " (existing session — orders will be combined)" : ""}
                  </p>
                )}
              </div>
            )}

            {/* Categories */}
            <div className="flex flex-wrap gap-1.5">
              <button
                onClick={() => setCatSel(null)}
                className={`rounded-full px-3.5 py-1.5 text-xs font-black transition ${catSel === null ? "bg-stone-800 text-white" : "border border-stone-200 bg-white text-stone-500 hover:border-stone-400"}`}
              >
                All
              </button>
              {cats.map((c) => (
                <button
                  key={c.id}
                  onClick={() => setCatSel(catSel === c.id ? null : c.id)}
                  className={`rounded-full px-3.5 py-1.5 text-xs font-black transition ${
                    catSel === c.id ? "bg-stone-800 text-white" : "border border-stone-200 bg-white text-stone-500 hover:border-stone-400"
                  }`}
                >
                  {c.name}
                </button>
              ))}
            </div>

            {/* Products */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 2xl:grid-cols-4">
              {shownCats.map((p) => (
                <button
                  key={p.id}
                  disabled={!p.available}
                  onClick={() => addToCart(p)}
                  className={`rounded-2xl border p-4 text-left transition ${
                    p.available
                      ? "border-stone-200 bg-white hover:-translate-y-0.5 hover:border-rasa-400 hover:shadow-md active:scale-[0.98]"
                      : "cursor-not-allowed border-stone-200 bg-stone-100 opacity-60"
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <p className="line-clamp-2 text-sm font-black text-ink">{p.name}</p>
                    <span className="shrink-0 text-[9px] font-black uppercase text-stone-300">+</span>
                  </div>
                  <p className="mt-1 text-sm font-black text-rasa-600">{rm(p.price)}</p>
                  {!p.available && <p className="text-[10px] font-bold uppercase text-stone-400">Sold out</p>}
                </button>
              ))}
              {shownCats.length === 0 && <p className="col-span-full py-8 text-center text-sm text-stone-400">No products yet. Add some to the menu first.</p>}
            </div>
          </div>

          {/* Right: cart */}
          <aside className="h-fit rounded-2xl border border-stone-200 bg-white p-4 xl:sticky xl:top-20">
            <p className="font-black text-ink">Order {type === "dine_in" ? `— Table ${tableSel?.number ?? "?"}` : "— Takeaway"}</p>
            <div className="mt-3 max-h-72 space-y-2 overflow-auto pr-1">
              {cart.length === 0 && <p className="py-6 text-center text-sm text-stone-400">Click a product to add it to the order.</p>}
              {cart.map((l) => (
                <div key={l.product.id} className="flex items-center gap-2 rounded-xl border border-stone-200 p-2">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold text-ink">{l.product.name}</p>
                    <p className="text-xs text-stone-500">{rm(Number(l.product.price) * l.qty)}</p>
                  </div>
                  <div className="flex items-center gap-1">
                    <button onClick={() => setQty(l.product.id, l.qty - 1)} className="grid h-6 w-6 place-items-center rounded-md border border-stone-200 text-sm font-black text-stone-500 hover:border-red-300 hover:text-red-500">
                      −
                    </button>
                    <span className="w-6 text-center text-sm font-black text-ink">{l.qty}</span>
                    <button onClick={() => setQty(l.product.id, l.qty + 1)} className="grid h-6 w-6 place-items-center rounded-md border border-stone-200 text-sm font-black text-stone-500 hover:border-rasa-300 hover:text-rasa-600">
                      +
                    </button>
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-3 flex items-center gap-2">
              <span className="text-xs font-black uppercase tracking-wide text-stone-500">Discount (RM)</span>
              <input
                type="number"
                min="0"
                step="0.05"
                value={discount}
                onChange={(e) => setDiscount(e.target.value)}
                placeholder="0.00"
                className="w-24 rounded-lg border border-stone-300 px-2 py-1 text-right text-sm font-bold outline-none focus:border-rasa-500"
              />
            </div>
            <input
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Order note (optional)…"
              className="mt-2 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-rasa-500"
            />

            <div className="mt-3 space-y-1 border-t border-stone-200 pt-3 text-sm">
              <div className="flex justify-between text-stone-500">
                <span>Subtotal</span>
                <span className="font-bold">{rm(subtotal)}</span>
              </div>
              {discountVal > 0 && (
                <div className="flex justify-between text-red-500">
                  <span>Discount</span>
                  <span className="font-bold">−{rm(discountVal)}</span>
                </div>
              )}
              <div className="flex justify-between text-lg font-black text-ink">
                <span>TOTAL</span>
                <span>{rm(total)}</span>
              </div>
            </div>

            <button onClick={() => void sendOrder()} disabled={sending || cart.length === 0 || !onDuty} className="mt-3 w-full rounded-xl bg-rasa-600 py-3 text-sm font-black text-white shadow-lg shadow-rasa-600/25 transition hover:bg-rasa-700 disabled:cursor-not-allowed disabled:opacity-50">
              {sending ? "Sending…" : `Send Order · ${rm(total)}`}
            </button>
            {cart.length > 0 && (
              <button onClick={() => setCart([])} className="mt-2 w-full rounded-xl border border-stone-200 py-2 text-xs font-bold text-stone-500 hover:border-red-200 hover:text-red-500">
                Clear
              </button>
            )}
          </aside>
        </div>
      ) : (
        /* Floor */
        <div className="space-y-4">
          <div className="flex flex-wrap items-center gap-1.5">
            {(["all", "new", "preparing", "ready", "served", "completed", "cancelled"] as const).map((s) => (
              <button
                key={s}
                onClick={() => setStatusFilter(s)}
                className={`rounded-full px-3 py-1.5 text-xs font-black transition ${
                  statusFilter === s ? "bg-stone-800 text-white" : "border border-stone-200 bg-white text-stone-500 hover:border-stone-400"
                }`}
              >
                {s === "all" ? `All (${orders.length})` : `${STATUS_LABEL[s] ?? s} (${orders.filter((o) => o.status === s).length})`}
              </button>
            ))}
          </div>

          <div className="space-y-3">
            {filtered.length === 0 && <div className="rounded-2xl border border-dashed border-stone-300 bg-white/50 p-12 text-center text-sm text-stone-400">No orders yet.</div>}
            {filtered.map((o) => {
              const fwd = FORWARD[o.status];
              const canPay = PAYABLE.has(o.status);
              const cancel = CANCEL_ALLOWED.has(o.status);
              return (
                <div key={o.id} className="rounded-2xl border border-stone-200 bg-white p-4">
                  <div className="flex flex-wrap items-center gap-3">
                    <p className="font-black text-ink">{o.order_no}</p>
                    <span className="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-black uppercase text-stone-500">{o.type_label}</span>
                    {o.table && <span className="text-xs font-bold text-rasa-600">Table {o.table.number}</span>}
                    <span className="text-xs text-stone-400">{timeOnly(o.created_at)}</span>
                    <span
                      className={`ml-auto rounded-full px-3 py-1 text-xs font-black ${
                        o.status === "completed"
                          ? "bg-emerald-100 text-emerald-700"
                          : o.status === "cancelled" || o.status === "voided"
                            ? "bg-red-100 text-red-600"
                            : "bg-rasa-100 text-rasa-600"
                      }`}
                    >
                      {o.status_label}
                    </span>
                    <p className="text-lg font-black text-ink">{rm(o.total)}</p>
                  </div>
                  <div className="mt-2 flex flex-wrap gap-2">
                    {fwd.next && (
                      <button onClick={() => void advance(o, fwd.next!)} className="rounded-lg bg-stone-800 px-4 py-1.5 text-xs font-black text-white transition hover:bg-stone-700">
                        {fwd.label}
                      </button>
                    )}
                    {cancel && (
                      <button onClick={() => void advance(o, "cancelled")} className="rounded-lg border border-red-200 px-4 py-1.5 text-xs font-black text-red-500 transition hover:bg-red-50">
                        Cancel order
                      </button>
                    )}
                    {canPay && (
                      <button onClick={() => void openPay(o)} className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-black text-white shadow transition hover:bg-emerald-500">
                        {o.type === "dine_in" && o.table ? "Table bill & pay" : "Pay"}
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Pay dialog */}
      {pay && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-stone-900/50 p-4" onClick={() => !pay.busy && setPay(null)}>
          <div className="max-h-[85vh] w-full max-w-md overflow-auto rounded-2xl border border-stone-200 bg-white p-6 shadow-2xl" onClick={(e) => e.stopPropagation()}>
            <p className="text-lg font-black text-ink">
              {pay.order ? `Pay ${pay.order.order_no}` : `Table bill ${pay.tableNumber ?? ""} 🧾`}
            </p>
            {!pay.bill ? (
              <p className="py-6 text-center text-sm text-stone-400">Loading bill…</p>
            ) : (
              <>
                <div className="mt-3 space-y-2">
                  {pay.bill.orders.map((o) => (
                    <div key={o.id} className="rounded-xl border border-stone-200 p-3">
                      <div className="flex items-center justify-between">
                        <p className="text-sm font-black text-ink">{o.order_no}</p>
                        <p className="text-sm font-black text-rasa-600">{rm(o.total)}</p>
                      </div>
                      {(o.items ?? []).map((i) => (
                        <p key={i.id} className="text-xs text-stone-500">
                          {i.quantity} × {i.name} — {rm(i.line_total)}
                        </p>
                      ))}
                    </div>
                  ))}
                </div>
                <div className="mt-4 flex items-center justify-between border-t border-stone-200 pt-3">
                  <p className="font-black text-ink">TOTAL</p>
                  <p className="text-xl font-black text-rasa-600">{rm(pay.bill.bill_total)}</p>
                </div>

                <p className="mb-2 mt-4 text-xs font-black uppercase tracking-wide text-stone-500">Payment method</p>
                <div className="grid grid-cols-2 gap-2">
                  {METHODS.map((m) => (
                    <button
                      key={m.m}
                      onClick={() => setPay({ ...pay, method: m.m })}
                      className={`rounded-xl border px-3 py-3 text-sm font-black transition ${
                        pay.method === m.m ? "border-rasa-500 bg-rasa-50 text-rasa-700 ring-2 ring-rasa-200" : "border-stone-200 text-stone-600 hover:border-rasa-300"
                      }`}
                    >
                      {m.label}
                    </button>
                  ))}
                </div>
                <button onClick={() => void confirmPay()} disabled={!pay.method || pay.busy} className="mt-4 w-full rounded-xl bg-rasa-600 py-3 text-sm font-black text-white shadow transition hover:bg-rasa-700 disabled:opacity-50">
                  {pay.busy ? "Processing…" : "Confirm Payment"}
                </button>
              </>
            )}
          </div>
        </div>
      )}
    </>
  );
}

export default function PosPage() {
  return (
    <AppShell active="pos">
      <PosPageContent />
    </AppShell>
  );
}
