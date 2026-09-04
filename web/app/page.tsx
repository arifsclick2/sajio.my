import Image from "next/image";
import Link from "next/link";

/* ------------------------------------------------------------------ */
/*  Shared bits                                                        */
/* ------------------------------------------------------------------ */

function Logo({ dark = false }: { dark?: boolean }) {
  return (
    <span className={`inline-flex items-center gap-2 text-xl font-extrabold tracking-tight ${dark ? "text-white" : "text-ink"}`}>
      <span className="grid h-8 w-8 place-items-center rounded-xl bg-gradient-to-br from-brand-600 to-emerald-500 text-sm font-black text-white shadow-md shadow-brand-600/30">
        S
      </span>
      sajio<span className="text-brand-600">.</span>
    </span>
  );
}

function Check({ className = "" }: { className?: string }) {
  return (
    <svg className={`h-5 w-5 shrink-0 text-brand-600 ${className}`} viewBox="0 0 20 20" fill="currentColor">
      <path
        fillRule="evenodd"
        d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z"
        clipRule="evenodd"
      />
    </svg>
  );
}

/* Decorative QR (looks real enough for mockups) */
function QrSvg({ className = "" }: { className?: string }) {
  const N = 15;
  const finder = (x: number, y: number, ox: number, oy: number) => {
    const dx = x - ox;
    const dy = y - oy;
    if (dx < 0 || dy < 0 || dx > 6 || dy > 6) return false;
    const edge = dx === 0 || dy === 0 || dx === 6 || dy === 6;
    const ring = dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4;
    return edge || ring;
  };
  const cells: string[] = [];
  for (let y = 0; y < N; y++) {
    let row = "";
    for (let x = 0; x < N; x++) {
      let on = false;
      if (finder(x, y, 0, 0) || finder(x, y, 8, 0) || finder(x, y, 0, 8)) on = true;
      else if (x >= 7 && y >= 7 && !(x >= 8 && y >= 8 && (x - 8) <= 6 && (y - 8) <= 6)) on = (x * 5 + y * 3 + x * y) % 4 === 0;
      else if (x >= 8 && y >= 8) {
        const dx = x - 8;
        const dy = y - 8;
        const edge = dx === 0 || dy === 0 || dx === 6 || dy === 6;
        const ring = dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4;
        on = edge || ring || (dx * 7 + dy * 11) % 3 === 0;
      } else on = (x + y * 9 + x * y) % 5 === 0;
      row += on ? "1" : "0";
    }
    cells.push(row);
  }
  return (
    <svg viewBox={`0 0 ${N} ${N}`} className={className} shapeRendering="crispEdges" aria-hidden="true">
      {cells.map((row, y) =>
        row.split("").map((c, x) => (c === "1" ? <rect key={`${x}-${y}`} x={x} y={y} width={1.02} height={1.02} fill="currentColor" /> : null)),
      )}
    </svg>
  );
}

function BrowserFrame({ url, children }: { url: string; children: React.ReactNode }) {
  return (
    <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-2xl shadow-brand-900/10">
      <div className="flex items-center gap-2 border-b border-stone-100 bg-stone-50 px-4 py-2.5">
        <span className="h-2.5 w-2.5 rounded-full bg-red-400" />
        <span className="h-2.5 w-2.5 rounded-full bg-gold-400" />
        <span className="h-2.5 w-2.5 rounded-full bg-emerald-400" />
        <span className="ml-3 flex-1 truncate rounded-md bg-white px-3 py-1 text-[11px] font-medium text-stone-400 ring-1 ring-stone-200">
          {url}
        </span>
      </div>
      {children}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Product mockups                                                    */
/* ------------------------------------------------------------------ */

function DashboardMock() {
  const bars = [38, 52, 44, 66, 58, 82, 96];
  const days = ["Isn", "Sel", "Rab", "Kha", "Jum", "Sab", "Aha"];
  const orders = [
    { id: "#5006", table: "Meja 12", item: "Nasi Lemak Ayam", status: "Sedia", tone: "bg-gold-100 text-gold-600" },
    { id: "#5007", table: "Bungkus", item: "Teh Tarik ×2", status: "Selesai", tone: "bg-emerald-100 text-emerald-700" },
    { id: "#5008", table: "Meja 3", item: "Roti Canai ×3", status: "Memasak", tone: "bg-sky-100 text-sky-700" },
  ];
  return (
    <div className="bg-[#FBF9F4] p-4 sm:p-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-wider text-stone-400">Khamis · 4 Sept 2026</p>
          <p className="text-sm font-bold text-ink">Selamat datang, Kopitiam Sajio ☕</p>
        </div>
        <span className="rounded-full bg-brand-600 px-3 py-1 text-[10px] font-bold text-white">PRO</span>
      </div>

      {/* KPIs */}
      <div className="mt-4 grid grid-cols-3 gap-2.5">
        <div className="rounded-xl bg-white p-3 ring-1 ring-stone-100">
          <p className="text-[10px] font-medium text-stone-400">Jualan Hari Ini</p>
          <p className="mt-0.5 text-sm font-extrabold text-ink">RM 2,480</p>
          <p className="mt-0.5 text-[10px] font-semibold text-emerald-600">▲ 12% vs semalam</p>
        </div>
        <div className="rounded-xl bg-white p-3 ring-1 ring-stone-100">
          <p className="text-[10px] font-medium text-stone-400">Pesanan</p>
          <p className="mt-0.5 text-sm font-extrabold text-ink">48</p>
          <p className="mt-0.5 text-[10px] font-semibold text-stone-400">15 pending</p>
        </div>
        <div className="rounded-xl bg-white p-3 ring-1 ring-stone-100">
          <p className="text-[10px] font-medium text-stone-400">Meja Aktif</p>
          <p className="mt-0.5 text-sm font-extrabold text-ink">6 / 10</p>
          <p className="mt-0.5 text-[10px] font-semibold text-brand-600">Sesi dibuka</p>
        </div>
      </div>

      <div className="mt-2.5 grid grid-cols-5 gap-2.5">
        {/* Chart */}
        <div className="col-span-3 rounded-xl bg-white p-3 ring-1 ring-stone-100">
          <p className="text-[10px] font-semibold text-stone-500">Jualan Minggu Ini</p>
          <div className="mt-3 flex h-20 items-end gap-1.5">
            {bars.map((h, i) => (
              <div key={i} className="flex-1 rounded-t-md bg-brand-100" style={{ height: `${h}%` }}>
                <div
                  className={`w-full rounded-t-md ${i === bars.length - 1 ? "bg-gradient-to-t from-brand-600 to-emerald-400" : "bg-brand-200"}`}
                  style={{ height: `${h}%` }}
                />
              </div>
            ))}
          </div>
          <div className="mt-1.5 flex justify-between">
            {days.map((d) => (
              <span key={d} className="text-[9px] font-medium text-stone-400">
                {d}
              </span>
            ))}
          </div>
        </div>
        {/* Payment split */}
        <div className="col-span-2 rounded-xl bg-white p-3 ring-1 ring-stone-100">
          <p className="text-[10px] font-semibold text-stone-500">Kaedah Bayaran</p>
          <div className="mt-2 space-y-1.5">
            {[
              ["Tunai", "62%"],
              ["QR / e-Wallet", "24%"],
              ["Kad", "14%"],
            ].map(([k, v]) => (
              <div key={k} className="flex items-center justify-between text-[10px]">
                <span className="font-medium text-stone-500">{k}</span>
                <span className="font-bold text-ink">{v}</span>
              </div>
            ))}
          </div>
          <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100">
            <div className="flex h-full">
              <span className="w-[62%] bg-brand-500" />
              <span className="w-[24%] bg-gold-400" />
              <span className="w-[14%] bg-rose-400" />
            </div>
          </div>
        </div>
      </div>

      {/* Orders */}
      <div className="mt-2.5 rounded-xl bg-white p-3 ring-1 ring-stone-100">
        <p className="text-[10px] font-semibold text-stone-500">Pesanan Terkini</p>
        <div className="mt-2 space-y-1.5">
          {orders.map((o) => (
            <div key={o.id} className="flex items-center justify-between rounded-lg bg-stone-50 px-2.5 py-1.5">
              <div className="flex items-center gap-2">
                <span className="text-[10px] font-bold text-stone-400">{o.id}</span>
                <span className="text-[11px] font-semibold text-ink">
                  {o.item} <span className="font-medium text-stone-400">· {o.table}</span>
                </span>
              </div>
              <span className={`rounded-full px-2 py-0.5 text-[9px] font-bold ${o.tone}`}>{o.status}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function PosMock() {
  const items = [
    { q: "1×", name: "Nasi Lemak Ayam Berempah", price: "12.50" },
    { q: "2×", name: "Teh Tarik", price: "6.00" },
    { q: "1×", name: "Roti Canai Telur", price: "2.50" },
  ];
  return (
    <div className="relative">
      <div className="mx-auto w-[340px] max-w-full rounded-[2rem] border-[6px] border-stone-800 bg-[#FBF9F4] p-4 shadow-2xl shadow-brand-900/20">
        <div className="flex items-center justify-between">
          <span className="rounded-lg bg-stone-800 px-2.5 py-1 text-[11px] font-extrabold text-white">MEJA 12</span>
          <span className="text-[11px] font-bold text-brand-600">Dine-in · Sesi #1052</span>
          <span className="text-[11px] font-semibold text-stone-400">7:15 PM</span>
        </div>
        <div className="mt-3 space-y-1.5">
          {items.map((it) => (
            <div key={it.name} className="flex items-center gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-stone-100">
              <span className="w-6 text-center text-[10px] font-black text-brand-600">{it.q}</span>
              <span className="flex-1 text-[12px] font-semibold text-ink">{it.name}</span>
              <span className="text-[12px] font-bold text-ink">RM {it.price}</span>
            </div>
          ))}
        </div>
        <div className="mt-3 flex items-center justify-between rounded-xl bg-brand-800 px-3 py-2.5 text-white">
          <span className="text-[11px] font-semibold text-brand-200">JUMLAH</span>
          <span className="text-lg font-black">RM 21.00</span>
        </div>
        <div className="mt-3 grid grid-cols-4 gap-1.5 text-center text-[10px] font-bold">
          <span className="rounded-lg bg-emerald-500 py-2 text-white">Tunai</span>
          <span className="rounded-lg bg-sky-500 py-2 text-white">Kad</span>
          <span className="rounded-lg bg-stone-800 py-2 text-white">QR</span>
          <span className="rounded-lg bg-gold-500 py-2 text-white">Wallet</span>
        </div>
        <div className="mt-3 rounded-xl bg-gradient-to-r from-brand-600 to-emerald-500 py-2.5 text-center text-[12px] font-black text-white shadow-lg shadow-brand-600/30">
          Bayar RM 21.00
        </div>
      </div>
      {/* Floating receipt */}
      <div className="absolute -right-2 -top-6 hidden rotate-3 rounded-lg bg-white p-2.5 shadow-xl ring-1 ring-stone-100 sm:block">
        <p className="text-[8px] font-black tracking-widest text-stone-400">SAJIO KOPITIAM</p>
        <div className="mt-1 space-y-0.5 text-[8px] text-stone-500">
          <p>Nasi Lemak Ayam … RM12.50</p>
          <p>Teh Tarik ×2 ………… RM6.00</p>
          <p>Roti Canai ………… RM2.50</p>
          <p className="border-t border-dashed border-stone-200 pt-0.5 font-black text-ink">TOTAL ……… RM21.00</p>
        </div>
        <p className="mt-1 rounded bg-emerald-50 px-1.5 py-0.5 text-center text-[8px] font-bold text-emerald-600">✓ BAYARAN SELESAI</p>
      </div>
    </div>
  );
}

function OrderPhoneMock() {
  const menu = [
    { e: "🍗", n: "Ayam Goreng Berempah", p: "9.90" },
    { e: "🍛", n: "Nasi Kandar Ikan", p: "11.00" },
    { e: "☕", n: "Teh Tarik", p: "2.50" },
    { e: "🥞", n: "Roti Canai", p: "1.80" },
  ];
  return (
    <div className="relative">
      <div className="mx-auto w-[270px] max-w-full rounded-[2.4rem] border-[6px] border-stone-800 bg-[#FBF9F4] p-3 shadow-2xl shadow-brand-900/20">
        <div className="flex items-center justify-between px-1">
          <span className="text-[12px] font-extrabold text-ink">Kedai Sajio</span>
          <span className="rounded-full bg-brand-100 px-2 py-0.5 text-[9px] font-black text-brand-700">MEJA 12</span>
        </div>
        <div className="mt-2 flex gap-1.5 text-[10px] font-bold">
          <span className="rounded-full bg-brand-700 px-2.5 py-1 text-white">Semua</span>
          <span className="rounded-full bg-white px-2.5 py-1 text-stone-500 ring-1 ring-stone-200">Makanan</span>
          <span className="rounded-full bg-white px-2.5 py-1 text-stone-500 ring-1 ring-stone-200">Minuman</span>
        </div>
        <div className="mt-2.5 space-y-1.5">
          {menu.map((m) => (
            <div key={m.n} className="flex items-center gap-2 rounded-xl bg-white px-2 py-1.5 ring-1 ring-stone-100">
              <span className="grid h-8 w-8 place-items-center rounded-lg bg-cream text-base">{m.e}</span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-[11px] font-bold text-ink">{m.n}</p>
                <p className="text-[10px] font-semibold text-brand-600">RM {m.p}</p>
              </div>
              <span className="grid h-5 w-5 place-items-center rounded-md bg-brand-600 text-[12px] font-black text-white">+</span>
            </div>
          ))}
        </div>
        <div className="mt-2.5 flex items-center justify-between rounded-xl bg-brand-800 px-3 py-2 text-white">
          <span className="text-[10px] font-semibold text-brand-200">🛒 4 item</span>
          <span className="text-[12px] font-black">Hantar · RM25.20</span>
        </div>
      </div>
      {/* Status float */}
      <div className="absolute -left-6 top-10 hidden rotate-[-4deg] rounded-xl bg-white px-3 py-2 shadow-xl ring-1 ring-stone-100 sm:block">
        <p className="text-[9px] font-bold text-stone-400">DAPUR · #5008</p>
        <p className="text-[10px] font-black text-ink">Memasak 🔥</p>
      </div>
    </div>
  );
}

function QrCustomerMock() {
  const items = [
    { n: "Nasi Lemak Ayam", p: "12.50" },
    { n: "Teh Tarik", p: "2.50" },
  ];
  return (
    <div className="relative">
      <div className="mx-auto w-[270px] max-w-full rounded-[2.4rem] border-[6px] border-stone-800 bg-[#FBF9F4] p-3 shadow-2xl shadow-brand-900/20">
        <div className="flex items-center justify-between px-1">
          <span className="text-[12px] font-extrabold text-ink">Menu · Meja 5</span>
          <span className="text-sm">🧑‍🍳</span>
        </div>
        <div className="mt-2 rounded-xl bg-gradient-to-br from-brand-700 to-emerald-600 p-2.5 text-center text-white">
          <p className="text-[9px] font-semibold text-brand-100">Scan untuk order — tiada app perlu</p>
          <QrSvg className="mx-auto mt-1.5 h-24 w-24 text-white" />
          <p className="mt-1 text-[9px] font-bold tracking-widest">sajio.my/order/t5k2</p>
        </div>
        <div className="mt-2 space-y-1.5">
          {items.map((m) => (
            <div key={m.n} className="flex items-center justify-between rounded-xl bg-white px-2.5 py-1.5 ring-1 ring-stone-100">
              <span className="text-[11px] font-bold text-ink">{m.n}</span>
              <span className="text-[11px] font-bold text-brand-600">RM {m.p}</span>
            </div>
          ))}
        </div>
        <div className="mt-1.5 flex items-center justify-between rounded-xl bg-gold-500 px-3 py-2 text-white">
          <span className="text-[10px] font-bold">JUMLAH</span>
          <span className="text-[12px] font-black">RM 15.00</span>
        </div>
        <div className="mt-1.5 rounded-xl bg-brand-800 py-2 text-center text-[11px] font-black text-white">Letak Pesanan ✓</div>
      </div>
      <div className="absolute -right-4 -bottom-4 hidden rotate-3 rounded-2xl bg-brand-700 px-4 py-3 text-white shadow-xl sm:block">
        <p className="text-[9px] font-semibold text-brand-200">Meja 5 sedang order</p>
        <p className="text-[11px] font-black">🔔 Pesanan baru #5010</p>
      </div>
    </div>
  );
}

function TableTagMock({ className = "" }: { className?: string }) {
  return (
    <div className={`rounded-2xl bg-gradient-to-br from-brand-800 via-brand-700 to-emerald-600 p-4 text-white shadow-xl ${className}`}>
      <div className="flex items-center justify-between">
        <span className="inline-flex items-center gap-1.5 text-[11px] font-black tracking-wide">
          <span className="grid h-4 w-4 place-items-center rounded bg-white text-[9px] font-black text-brand-700">S</span>
          SAJIO
        </span>
        <span className="rounded-full bg-white/15 px-2 py-0.5 text-[9px] font-bold">NFC-ready</span>
      </div>
      <p className="mt-3 text-[9px] font-semibold uppercase tracking-[0.2em] text-brand-200">Table Token</p>
      <div className="flex items-end justify-between">
        <p className="text-4xl font-black leading-none">25</p>
        <QrSvg className="h-16 w-16 rounded bg-white p-1 text-stone-900" />
      </div>
      <p className="mt-2 text-[9px] font-medium text-brand-200">Imbas untuk bil & pesanan meja ini · T25-8F4K2</p>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Page                                                               */
/* ------------------------------------------------------------------ */

const navLinks = [
  { href: "#produk", label: "Produk" },
  { href: "#cara", label: "Cara Guna" },
  { href: "#ciri", label: "Ciri-ciri" },
  { href: "#malaysia", label: "Malaysia" },
  { href: "#pakej", label: "Pakej" },
];

const cuisines = ["🍜 Warung & Mamak", "☕ Kopitiam & Kafe", "🍚 Restoran Keluarga", "🍗 Gerai & Food Court", "🥡 Takeaway & Bungkus"];

export default function HomePage() {
  return (
    <>
      {/* ================= NAV ================= */}
      <header className="sticky top-0 z-40 border-b border-stone-200/70 bg-paper/85 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
          <Link href="/" aria-label="Sajio home">
            <Logo />
          </Link>
          <nav className="hidden items-center gap-7 text-sm font-medium text-stone-600 lg:flex">
            {navLinks.map((l) => (
              <a key={l.href} href={l.href} className="transition hover:text-brand-700">
                {l.label}
              </a>
            ))}
          </nav>
          <div className="flex items-center gap-2.5">
            <Link href="/login" className="rounded-xl px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-200/60 hover:text-ink">
              Log masuk
            </Link>
            <Link
              href="/register"
              className="rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-brand-700/25 transition hover:bg-brand-800"
            >
              Cuba percuma
            </Link>
          </div>
        </div>
      </header>

      <main className="flex-1">
        {/* ================= HERO ================= */}
        <section className="pattern-batik relative overflow-hidden">
          <div className="pointer-events-none absolute -top-32 left-1/2 h-96 w-[52rem] -translate-x-1/2 rounded-full bg-brand-200/40 blur-3xl" />
          <div className="pointer-events-none absolute -left-24 top-64 h-64 w-64 rounded-full bg-gold-300/30 blur-3xl" />
          <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pb-16 pt-14 sm:px-6 lg:grid-cols-2 lg:gap-8 lg:pb-24 lg:pt-20">
            <div>
              <p className="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white px-4 py-1.5 text-xs font-bold text-brand-800 shadow-sm">
                🇲🇾 Dibuat untuk restoran, kafe &amp; warung Malaysia
              </p>
              <h1 className="mt-5 text-4xl font-black leading-[1.08] tracking-tight text-ink sm:text-5xl lg:text-[3.4rem]">
                Simple POS.{" "}
                <span className="bg-gradient-to-r from-brand-700 via-brand-600 to-emerald-500 bg-clip-text text-transparent">
                  Simple Ordering.
                </span>{" "}
                Simple Business.
              </h1>
              <p className="mt-5 max-w-xl text-lg leading-relaxed text-stone-600">
                Dari nasi lemak stall ke kopitiam keluarga — Sajio helps you manage <strong className="font-bold text-ink">meja, pesanan, bayaran dan duit harian</strong> dalam satu sistem yang mudah. Staff belajar dalam beberapa minit, bukan beberapa hari.
              </p>
              <div className="mt-8 flex flex-wrap items-center gap-3.5">
                <Link
                  href="/register"
                  className="rounded-2xl bg-brand-700 px-7 py-3.5 text-base font-bold text-white shadow-xl shadow-brand-700/30 transition hover:-translate-y-0.5 hover:bg-brand-800"
                >
                  Mula 14 hari percuma
                </Link>
                <a
                  href="#produk"
                  className="rounded-2xl border border-stone-300 bg-white px-7 py-3.5 text-base font-semibold text-ink transition hover:border-brand-300 hover:text-brand-700"
                >
                  Lihat produk →
                </a>
              </div>
              <p className="mt-5 text-sm font-medium text-stone-500">Tiada kad kredit · Setup dalam 15 minit · Batalkan bila-bila</p>
              <div className="mt-6 flex flex-wrap gap-2">
                {cuisines.map((c) => (
                  <span key={c} className="rounded-full border border-stone-200 bg-white px-3 py-1 text-xs font-semibold text-stone-600">
                    {c}
                  </span>
                ))}
              </div>
            </div>

            {/* Hero visual */}
            <div className="relative">
              <div className="pointer-events-none absolute -inset-6 rounded-[2.5rem] bg-gradient-to-br from-brand-100 via-white to-gold-200/60 blur-2xl" />
              <div className="relative">
                <BrowserFrame url="app.sajio.my — Kopitiam Sajio">
                  <DashboardMock />
                </BrowserFrame>
                <div className="absolute -left-4 -bottom-8 hidden -rotate-3 rounded-2xl bg-white px-4 py-3 shadow-xl ring-1 ring-stone-100 lg:block">
                  <p className="text-[9px] font-bold uppercase tracking-wider text-stone-400">Pembayaran diterima</p>
                  <p className="text-lg font-black text-emerald-600">RM 21.00 ✓</p>
                  <p className="text-[10px] font-medium text-stone-500">Tunai · Meja 12 · 7:16 PM</p>
                </div>
                <div className="absolute -right-3 -top-5 hidden rotate-3 rounded-2xl bg-white px-4 py-3 shadow-xl ring-1 ring-stone-100 lg:block">
                  <p className="text-[9px] font-bold uppercase tracking-wider text-stone-400">Live order</p>
                  <p className="text-[11px] font-black text-ink">🔔 #5010 · Meja 5</p>
                  <p className="text-[10px] font-medium text-stone-500">Nasi Lemak Ayam ×2</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ================= PRODUCT ROWS ================= */}
        <section id="produk" className="scroll-mt-20 bg-white py-20">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-black uppercase tracking-[0.25em] text-brand-600">Produk</p>
              <h2 className="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Satu sistem untuk setiap peranan</h2>
              <p className="mt-4 text-stone-600">Kamera, waiter, cashier dan owner — semua kerja atas satu platform yang pantas dan mudah.</p>
            </div>

            {/* Row 1 — POS */}
            <div className="mt-16 grid items-center gap-10 lg:grid-cols-2">
              <div className="order-2 lg:order-1">
                <div className="relative mx-auto max-w-md lg:max-w-none">
                  <div className="pointer-events-none absolute inset-0 -rotate-2 rounded-[2.5rem] bg-brand-100/60" />
                  <PosMock />
                </div>
              </div>
              <div className="order-1 lg:order-2">
                <span className="inline-block rounded-full bg-brand-50 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-brand-700 ring-1 ring-brand-200">
                  POS · Dine-in &amp; Takeaway
                </span>
                <h3 className="mt-4 text-2xl font-black tracking-tight text-ink sm:text-3xl">POS yang laju untuk hari yang sibuk</h3>
                <p className="mt-4 leading-relaxed text-stone-600">
                  Buat order, pilih meja, scan table tag dan ambil bayaran — semua dengan beberapa ketukan sahaja. Sesuai untuk skrin sentuh, tablet atau PC.
                </p>
                <ul className="mt-6 space-y-3">
                  {["Buka & tutup sesi meja automatik", "Tunai, Kad, QR & e-Wallet — rekod bayaran mudah", "Resit terus dicetak di pelayar", "Takeaway & bungkus tanpa meja"].map((t) => (
                    <li key={t} className="flex items-start gap-2.5 text-[15px] font-medium text-stone-700">
                      <Check /> {t}
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            {/* Row 2 — Staff ordering */}
            <div className="mt-24 grid items-center gap-10 lg:grid-cols-2">
              <div>
                <span className="inline-block rounded-full bg-sky-50 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-sky-700 ring-1 ring-sky-200">
                  Staff Ordering
                </span>
                <h3 className="mt-4 text-2xl font-black tracking-tight text-ink sm:text-3xl">Order dari mana-mana, sampai ke dapur serta-merta</h3>
                <p className="mt-4 leading-relaxed text-stone-600">
                  Waiter guna telefon atau tablet mereka — pilih meja, tambah makanan dan hantar terus ke dapur. Tiada lagi berlari ke belakang atau tulis nota hilang.
                </p>
                <ul className="mt-6 space-y-3">
                  {["Kategori & menu yang besar dan mudah ditekan", "Order terus sampai ke dapur — status hidup", "Sokongan Android, iPhone, tablet & desktop", "Nota khas untuk dapur (kurang pedas, no ice)"].map((t) => (
                    <li key={t} className="flex items-start gap-2.5 text-[15px] font-medium text-stone-700">
                      <Check /> {t}
                    </li>
                  ))}
                </ul>
              </div>
              <div>
                <div className="relative mx-auto max-w-md lg:max-w-none">
                  <div className="pointer-events-none absolute inset-0 rotate-2 rounded-[2.5rem] bg-sky-100/60" />
                  <OrderPhoneMock />
                </div>
              </div>
            </div>

            {/* Row 3 — Customer QR */}
            <div className="mt-24 grid items-center gap-10 lg:grid-cols-2">
              <div className="order-2 lg:order-1">
                <div className="relative mx-auto max-w-md lg:max-w-none">
                  <div className="pointer-events-none absolute inset-0 -rotate-2 rounded-[2.5rem] bg-gold-200/50" />
                  <QrCustomerMock />
                </div>
              </div>
              <div className="order-1 lg:order-2">
                <span className="inline-block rounded-full bg-gold-100 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-gold-600 ring-1 ring-gold-300">
                  Customer QR Ordering · Premium &amp; Pro
                </span>
                <h3 className="mt-4 text-2xl font-black tracking-tight text-ink sm:text-3xl">Pelanggan scan, order sendiri</h3>
                <p className="mt-4 leading-relaxed text-stone-600">
                  Letak kad QR atas meja. Pelanggan scan dengan telefon mereka, tengok menu, pilih dan hantar order — tanpa perlu download app atau buat akaun.
                </p>
                <ul className="mt-6 space-y-3">
                  {["Menu mobile yang cantik — gambar, kategori, harga RM", "Order terus masuk ke dapur anda", "Staf kurang tertekan waktu rush hour", "Premium & Pro sahaja (pakej Basic? upgrade bila perlu)"].map((t) => (
                    <li key={t} className="flex items-start gap-2.5 text-[15px] font-medium text-stone-700">
                      <Check /> {t}
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            {/* Row 4 — Table Tag Pro */}
            <div className="mt-24 grid items-center gap-10 lg:grid-cols-2">
              <div>
                <span className="inline-block rounded-full bg-stone-800 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-gold-300">
                  Table Card / Table Tag · Pro
                </span>
                <h3 className="mt-4 text-2xl font-black tracking-tight text-ink sm:text-3xl">Scan tag meja, bil terus keluar</h3>
                <p className="mt-4 leading-relaxed text-stone-600">
                  Setiap meja ada kad fizikal dengan QR (dan sedia NFC). Cashier scan untuk buka bil meja itu serta-merta — istimewa untuk kedai makan Malaysia yang sibuk.
                </p>
                <ul className="mt-6 space-y-3">
                  {["Fizikal tag tidak sama dengan meja — boleh tukar & guna semula", "Scan QR di POS untuk buka bil pantas", "Backend sedia NFC — tambah NFC tag bila-bila masa", "Cetak kad meja dengan jenama restoran anda"].map((t) => (
                    <li key={t} className="flex items-start gap-2.5 text-[15px] font-medium text-stone-700">
                      <Check /> {t}
                    </li>
                  ))}
                </ul>
              </div>
              <div>
                <div className="relative mx-auto max-w-md lg:max-w-none">
                  <div className="pointer-events-none absolute inset-0 rotate-2 rounded-[2.5rem] bg-stone-200/70" />
                  <div className="relative">
                    <TableTagMock className="mx-auto w-72 -rotate-3" />
                    <div className="absolute -bottom-8 left-4 hidden rotate-2 rounded-xl bg-white px-4 py-2.5 shadow-lg ring-1 ring-stone-100 lg:block">
                      <p className="text-[10px] font-black text-ink">🔓 Sesi #1052 dibuka</p>
                      <p className="text-[9px] font-medium text-stone-500">Bil RM 87.00 · 3 pesanan</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ================= MADE FOR MALAYSIA ================= */}
        <section id="malaysia" className="pattern-batik scroll-mt-20 py-20">
          <div className="mx-auto grid max-w-6xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
            <div>
              <span className="inline-block rounded-full bg-white px-3 py-1 text-[11px] font-black uppercase tracking-wider text-brand-700 ring-1 ring-brand-200">
                Dibina untuk Malaysia
              </span>
              <h2 className="mt-4 text-3xl font-black tracking-tight text-ink sm:text-4xl">Cara Malaysia, bukan sekadar terjemahan</h2>
              <p className="mt-4 leading-relaxed text-stone-600">
                Sajio bermula dengan keperluan sebenar kedai makan tempatan — mata wang, waktu, cara bayar dan cara berniaga di Malaysia.
              </p>
              <div className="mt-7 grid gap-4 sm:grid-cols-2">
                {[
                  ["💰", "Ringgit & masa Malaysia", "Harga dalam RM, waktu Asia/Kuala_Lumpur, resit dan laporan format tempatan."],
                  ["🧾", "SST & e-Invoice-ready", "Tetapan cukai fleksibel berasaskan data — bukan hardcode. Bersedia untuk e-Invoice bila tiba masanya."],
                  ["📱", "Bayaran tempatan", "Catat tunai, kad, DuitNow QR dan e-wallet — cara rakyat Malaysia bayar."],
                  ["🌙", "Waktu operasi sebenar", "Buka hingga lewat malam? Sesi meja, shift dan laporan ikut cara anda beroperasi."],
                ].map(([e, t, d]) => (
                  <div key={t} className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <span className="text-2xl">{e}</span>
                    <h3 className="mt-2.5 text-[15px] font-black text-ink">{t}</h3>
                    <p className="mt-1.5 text-sm leading-relaxed text-stone-600">{d}</p>
                  </div>
                ))}
              </div>
            </div>

            {/* Photo collage */}
            <div className="relative">
              <div className="grid grid-cols-2 gap-4">
                <div className="relative h-64 overflow-hidden rounded-3xl shadow-xl sm:h-80">
                  <Image
                    src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=800&q=80"
                    alt="Restoran keluarga di Malaysia"
                    fill
                    sizes="(max-width: 640px) 50vw, 320px"
                    className="object-cover"
                  />
                </div>
                <div className="relative mt-10 h-64 overflow-hidden rounded-3xl shadow-xl sm:h-80">
                  <Image
                    src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=800&q=80"
                    alt="Staf kafe menyediakan minuman"
                    fill
                    sizes="(max-width: 640px) 50vw, 320px"
                    className="object-cover"
                  />
                </div>
              </div>
              <div className="absolute -bottom-5 left-1/2 flex -translate-x-1/2 items-center gap-3 rounded-2xl bg-white px-5 py-3.5 shadow-2xl ring-1 ring-stone-100">
                <span className="text-2xl">☕</span>
                <div>
                  <p className="text-sm font-black text-ink">Meja 5 order teh tarik</p>
                  <p className="text-xs font-semibold text-emerald-600">Dihantar ke dapur · 2 minit lalu</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ================= FEATURES ================= */}
        <section id="ciri" className="scroll-mt-20 bg-white py-20">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-black uppercase tracking-[0.25em] text-brand-600">Ciri-ciri</p>
              <h2 className="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Semua yang restoran perlu. Tiada yang lebih.</h2>
              <p className="mt-4 text-stone-600">Dari order pertama ke laporan hujung hari — tanpa kerumitan ala-ERP.</p>
            </div>
            <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {[
                ["🧑‍🍳", "Staff & Peranan", "Owner, manager dan staff — setiap peranan ada akses yang betul. Urus staff dengan mudah."],
                ["🍽️", "Pengurusan Meja", "Nombor meja, kapasiti, status Available/Occupied dan sesi semasa — semua nampak jelas."],
                ["📊", "Jualan & Perbelanjaan", "Jualan kasar, diskaun, cukai dan perbelanjaan harian. Ringkasan duit perniagaan yang mudah."],
                ["📈", "Laporan", "Jualan harian, mingguan, bulanan — produk, kategori dan kaedah bayaran. Selamat ikut restoran."],
                ["🧾", "Resit & Cetakan", "Resit pelayar yang bersih dengan logo restoran anda. Sedia untuk printer terma kemudian."],
                ["🛡️", "Data Selamat", "Setiap restoran diasingkan sepenuhnya. Data anda, hanya untuk anda."],
              ].map(([e, t, d]) => (
                <div
                  key={t}
                  className="group rounded-2xl border border-stone-200 bg-white p-6 transition hover:-translate-y-1 hover:border-brand-300 hover:shadow-xl hover:shadow-brand-600/10"
                >
                  <span className="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-2xl ring-1 ring-brand-100 transition group-hover:bg-brand-100">
                    {e}
                  </span>
                  <h3 className="mt-4 text-lg font-black text-ink">{t}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-stone-600">{d}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ================= HOW IT WORKS ================= */}
        <section id="cara" className="scroll-mt-20 py-20">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-black uppercase tracking-[0.25em] text-brand-600">Cara Guna</p>
              <h2 className="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Dari setup ke resit dalam 3 langkah</h2>
            </div>
            <div className="mt-14 grid gap-6 md:grid-cols-3">
              {[
                ["01", "Set up restoran anda", "Tambah menu, meja dan staff dalam beberapa minit. Default Malaysia: RM & Asia/Kuala_Lumpur.", "🍳"],
                ["02", "Mula terima order", "Guna POS, biar staff order dari tablet, atau pelanggan scan QR atas meja untuk order sendiri.", "📲"],
                ["03", "Dapat bayaran & pantau", "Tunai, kad atau QR. Resit keluar, sesi tutup dan jualan direkod automatik.", "💰"],
              ].map(([n, t, d, e]) => (
                <div key={n} className="relative rounded-3xl border border-stone-200 bg-white p-7 shadow-sm transition hover:shadow-lg">
                  <div className="flex items-center justify-between">
                    <span className="text-4xl font-black text-brand-100">{n}</span>
                    <span className="text-3xl">{e}</span>
                  </div>
                  <h3 className="mt-4 text-lg font-black text-ink">{t}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-stone-600">{d}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ================= PRICING ================= */}
        <section id="pakej" className="scroll-mt-20 bg-white py-20">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-black uppercase tracking-[0.25em] text-brand-600">Pakej</p>
              <h2 className="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Pilih pakej yang sesuai</h2>
              <p className="mt-4 text-stone-600">Semua pakej bermula dengan 14 hari percuma. Harga akan diumumkan — naik taraf bila-bila.</p>
            </div>
            <div className="mt-14 grid gap-6 md:grid-cols-3">
              {[
                {
                  name: "Basic",
                  blurb: "Untuk kedai yang baru mula",
                  feats: ["5 staff & 1 POS device", "Sehingga 10 meja & 100 item menu", "POS + staff table ordering", "Jualan, perbelanjaan & laporan asas"],
                  featured: false,
                },
                {
                  name: "Premium",
                  blurb: "Untuk kafe & restoran yang sibuk",
                  feats: ["Semua dalam Basic", "Customer QR ordering", "3 POS devices & 30 meja", "Laporan lanjutan"],
                  featured: true,
                },
                {
                  name: "Pro",
                  blurb: "Untuk operasi F&B yang serius",
                  feats: ["Semua dalam Premium", "Table Card / Tag system + QR/NFC", "Fast table scan di POS", "Cetak kad meja"],
                  featured: false,
                },
              ].map((p) => (
                <div
                  key={p.name}
                  className={`relative flex flex-col rounded-3xl border p-8 ${
                    p.featured
                      ? "border-brand-700 bg-brand-800 text-white shadow-2xl shadow-brand-800/30"
                      : "border-stone-200 bg-white shadow-sm"
                  }`}
                >
                  {p.featured && (
                    <span className="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-gold-500 px-4 py-1 text-xs font-black text-white shadow-md">
                      ⭐ PALING POPULAR
                    </span>
                  )}
                  <h3 className={`text-xl font-black ${p.featured ? "text-white" : "text-ink"}`}>{p.name}</h3>
                  <p className={`mt-1 text-sm ${p.featured ? "text-brand-200" : "text-stone-500"}`}>{p.blurb}</p>
                  <p className={`mt-4 text-3xl font-black tracking-tight ${p.featured ? "text-gold-300" : "text-ink"}`}>
                    RM <span className="text-lg font-bold opacity-70">harga TBD</span>
                  </p>
                  <ul className={`mt-6 flex-1 space-y-3 text-sm ${p.featured ? "text-brand-50" : "text-stone-700"}`}>
                    {p.feats.map((f) => (
                      <li key={f} className="flex items-start gap-2.5">
                        <Check className={p.featured ? "text-gold-300" : ""} />
                        {f}
                      </li>
                    ))}
                  </ul>
                  <Link
                    href="/register"
                    className={`mt-8 block rounded-2xl px-5 py-3.5 text-center text-sm font-black transition ${
                      p.featured ? "bg-gold-500 text-white hover:bg-gold-400" : "bg-brand-700 text-white hover:bg-brand-800"
                    }`}
                  >
                    Cuba percuma 14 hari
                  </Link>
                </div>
              ))}
            </div>
            <p className="mt-8 text-center text-sm font-medium text-stone-500">
              Semua pakej termasuk: pengurusan meja, menu, POS & laporan. Harga akan diumumkan tidak lama lagi.
            </p>
          </div>
        </section>

        {/* ================= CTA ================= */}
        <section className="py-20">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="pattern-batik-gold relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-brand-800 via-brand-700 to-emerald-600 px-6 py-16 text-center text-white sm:px-12">
              <div className="pointer-events-none absolute -top-24 right-0 h-72 w-72 rounded-full bg-white/10 blur-3xl" />
              <div className="pointer-events-none absolute -bottom-24 -left-10 h-72 w-72 rounded-full bg-gold-400/20 blur-3xl" />
              <div className="relative">
                <p className="text-3xl">☕</p>
                <h2 className="mx-auto mt-4 max-w-2xl text-3xl font-black leading-tight tracking-tight sm:text-4xl">Bersedia untuk jualan yang lebih lancar?</h2>
                <p className="mx-auto mt-4 max-w-xl text-brand-100">
                  Set up menu, meja dan staff anda hari ini. Lihat order pertama dalam masa 15 minit — atau nikmati teh tarik dulu, kami tunggu. 😉
                </p>
                <div className="mt-8 flex flex-wrap items-center justify-center gap-3.5">
                  <Link
                    href="/register"
                    className="rounded-2xl bg-white px-8 py-4 text-base font-black text-brand-800 shadow-xl transition hover:-translate-y-0.5 hover:bg-brand-50"
                  >
                    Mula 14 hari percuma
                  </Link>
                  <a href="#pakej" className="rounded-2xl border border-white/40 px-8 py-4 text-base font-bold text-white transition hover:bg-white/10">
                    Lihat pakej
                  </a>
                </div>
                <p className="mt-6 text-sm font-medium text-brand-200">Tiada kad kredit · Setup 15 minit · Batal bila-bila</p>
              </div>
            </div>
          </div>
        </section>
      </main>

      {/* ================= FOOTER ================= */}
      <footer className="border-t border-stone-200 bg-white">
        <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
          <div className="flex flex-col items-center justify-between gap-8 md:flex-row md:items-start">
            <div className="max-w-xs text-center md:text-left">
              <Logo />
              <p className="mt-3 text-sm leading-relaxed text-stone-500">
                Simple POS. Simple Ordering. Simple Business. Untuk restoran, kafe dan warung Malaysia.
              </p>
            </div>
            <div className="flex gap-14 text-sm">
              <div>
                <p className="font-black text-ink">Produk</p>
                <ul className="mt-3 space-y-2 text-stone-500">
                  <li><a href="#produk" className="transition hover:text-brand-700">POS</a></li>
                  <li><a href="#produk" className="transition hover:text-brand-700">QR Ordering</a></li>
                  <li><a href="#produk" className="transition hover:text-brand-700">Table Tags</a></li>
                  <li><a href="#pakej" className="transition hover:text-brand-700">Pakej</a></li>
                </ul>
              </div>
              <div>
                <p className="font-black text-ink">Akaun</p>
                <ul className="mt-3 space-y-2 text-stone-500">
                  <li><Link href="/login" className="transition hover:text-brand-700">Log masuk</Link></li>
                  <li><Link href="/register" className="transition hover:text-brand-700">Cuba percuma</Link></li>
                </ul>
              </div>
            </div>
          </div>
          <div className="mt-10 flex flex-col items-center justify-between gap-3 border-t border-stone-100 pt-6 text-xs text-stone-400 sm:flex-row">
            <p>© {new Date().getFullYear()} Sajio.my · Simple POS. Simple Ordering. Simple Business.</p>
            <p>Dibuat dengan ☕ dan ❤️ di Malaysia 🇲🇾</p>
          </div>
        </div>
      </footer>
    </>
  );
}
