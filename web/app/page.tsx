import Image from "next/image";
import Link from "next/link";
import PricingCards from "../components/landing/PricingCards";

/* ------------------------------------------------------------------ */
/*  Shared bits                                                        */
/* ------------------------------------------------------------------ */

function Logo({ light = false }: { light?: boolean }) {
  return (
    <span className={`inline-flex items-center gap-2 text-xl font-extrabold tracking-tight ${light ? "text-white" : "text-ink"}`}>
      <span className="grid h-8 w-8 place-items-center rounded-xl bg-rasa-500 text-sm font-black text-white shadow-md shadow-rasa-500/30">
        S
      </span>
      sajio<span className="text-rasa-500">.</span>
    </span>
  );
}

function Check({ light = false, className = "" }: { light?: boolean; className?: string }) {
  return (
    <svg className={`h-5 w-5 shrink-0 ${light ? "text-rasa-300" : "text-rasa-600"} ${className}`} viewBox="0 0 20 20" fill="currentColor">
      <path
        fillRule="evenodd"
        d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z"
        clipRule="evenodd"
      />
    </svg>
  );
}

const navLinks = [
  { href: "#product", label: "Product" },
  { href: "#how", label: "How it works" },
  { href: "#pricing", label: "Pricing" },
];

/* ------------------------------------------------------------------ */
/*  Page                                                               */
/* ------------------------------------------------------------------ */

export default function HomePage() {
  return (
    <>
      {/* ================= NAV ================= */}
      <header className="sticky top-0 z-40 border-b border-rasa-900/10 bg-[#fdf8f6]/85 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
          <Link href="/" aria-label="Sajio home">
            <Logo />
          </Link>
          <nav className="hidden items-center gap-8 text-sm font-semibold text-stone-600 md:flex">
            {navLinks.map((l) => (
              <a key={l.href} href={l.href} className="transition hover:text-rasa-600">
                {l.label}
              </a>
            ))}
          </nav>
          <div className="flex items-center gap-2">
            <Link href="/login" className="rounded-xl px-3.5 py-2 text-sm font-semibold text-stone-700 transition hover:text-ink">
              Log in
            </Link>
            <Link
              href="/register"
              className="rounded-xl bg-rasa-500 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-rasa-500/25 transition hover:-translate-y-0.5 hover:bg-rasa-600"
            >
              Start free
            </Link>
          </div>
        </div>
      </header>

      <main className="flex-1">
        {/* ================= HERO ================= */}
        <section className="relative overflow-hidden">
          <div className="pointer-events-none absolute -right-40 -top-40 h-[34rem] w-[34rem] rounded-full bg-rasa-100/70 blur-3xl" />
          <div className="pointer-events-none absolute -left-32 top-80 h-80 w-80 rounded-full bg-gold-300/25 blur-3xl" />
          <div className="relative mx-auto grid max-w-6xl items-center gap-14 px-4 pb-20 pt-14 sm:px-6 lg:grid-cols-2 lg:gap-10 lg:pb-28 lg:pt-20">
            {/* Copy */}
            <div>
              <p className="inline-flex items-center gap-2 rounded-full border border-rasa-200 bg-white px-4 py-1.5 text-xs font-bold text-rasa-600 shadow-sm">
                🇲🇾 Made for Malaysian kopitiams, cafes & warungs
              </p>
              <h1 className="mt-6 text-4xl font-black leading-[1.05] tracking-tight text-ink sm:text-5xl lg:text-[3.6rem]">
                Simple POS. Simple Ordering.{" "}
                <span className="text-rasa-500">Simple Business.</span>
              </h1>
              <p className="mt-5 max-w-lg text-lg leading-relaxed text-stone-600">
                Tables, orders, payments and daily sales — one fast system your staff can master in minutes.
              </p>
              <div className="mt-8 flex flex-wrap items-center gap-3">
                <Link
                  href="/register"
                  className="rounded-2xl bg-rasa-500 px-7 py-3.5 text-base font-bold text-white shadow-xl shadow-rasa-500/30 transition hover:-translate-y-0.5 hover:bg-rasa-600"
                >
                  Start 14-day free trial
                </Link>
                <a
                  href="#product"
                  className="rounded-2xl border border-stone-300 bg-white px-7 py-3.5 text-base font-semibold text-ink transition hover:border-rasa-300 hover:text-rasa-600"
                >
                  See it in action
                </a>
              </div>
              <div className="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-stone-500">
                <span className="inline-flex items-center gap-1.5"><Check className="h-4 w-4" /> No credit card</span>
                <span className="inline-flex items-center gap-1.5"><Check className="h-4 w-4" /> 15-minute setup</span>
                <span className="inline-flex items-center gap-1.5"><Check className="h-4 w-4" /> Cancel anytime</span>
              </div>
            </div>

            {/* Real POS photo */}
            <div className="relative">
              <div className="pointer-events-none absolute -inset-5 rounded-[2.6rem] bg-gradient-to-br from-rasa-200 via-transparent to-gold-200/70 blur-2xl" />
              <div className="relative mx-auto max-w-md lg:max-w-none">
                <div className="overflow-hidden rounded-[2rem] border-[6px] border-stone-900 bg-stone-900 shadow-2xl shadow-stone-900/30">
                  <div className="flex items-center justify-between border-b border-white/10 px-4 py-2.5">
                    <span className="text-[10px] font-black uppercase tracking-[0.2em] text-white/70">Sajio POS</span>
                    <span className="flex gap-1.5">
                      <span className="h-2 w-2 rounded-full bg-white/20" />
                      <span className="h-2 w-2 rounded-full bg-white/20" />
                    </span>
                  </div>
                  <div className="relative aspect-[4/3] w-full">
                    <Image
                      src="https://images.unsplash.com/photo-1556740738-b6a63e27c4df?auto=format&fit=crop&w=1200&q=80"
                      alt="Waiter taking a card payment on a Sajio POS at a busy cafe"
                      fill
                      sizes="(max-width: 1024px) 90vw, 44vw"
                      className="object-cover"
                      priority
                    />
                    <div className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-stone-900/60 to-transparent" />
                  </div>
                </div>

                {/* Floats */}
                <div className="absolute -left-4 -bottom-7 hidden -rotate-2 rounded-2xl border border-stone-100 bg-white px-4 py-3 shadow-xl lg:block">
                  <p className="text-[10px] font-bold uppercase tracking-wider text-stone-400">Payment received</p>
                  <p className="text-lg font-black text-rasa-500">RM 21.00</p>
                  <p className="text-[10px] font-medium text-stone-500">DuitNow QR · Table 12</p>
                </div>
                <div className="absolute -right-3 -top-4 hidden rotate-2 rounded-2xl border border-stone-100 bg-white px-4 py-3 shadow-xl lg:block">
                  <p className="text-[10px] font-bold uppercase tracking-wider text-stone-400">Kitchen</p>
                  <p className="text-[11px] font-black text-ink">#5012 · Table 5</p>
                  <p className="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600">
                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Being prepared
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Who it's for strip */}
          <div className="border-y border-stone-200/70 bg-white/70">
            <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-8 gap-y-2 px-4 py-4 text-sm font-bold text-stone-500 sm:px-6">
              <span className="text-xs font-black uppercase tracking-[0.2em] text-stone-400">Made for</span>
              <span>☕ Kopitiam</span>
              <span>🍜 Mamak & warung</span>
              <span>🍚 Family restaurants</span>
              <span>🥡 Takeaway & bungkus</span>
            </div>
          </div>
        </section>

        {/* ================= PRODUCT CARDS ================= */}
        <section id="product" className="scroll-mt-20 bg-white py-20 sm:py-24">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-black uppercase tracking-[0.25em] text-rasa-500">Product</p>
              <h2 className="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Everything you need on the floor</h2>
              <p className="mt-4 text-stone-600">Three ways to take an order — take the payment, print the receipt, done.</p>
            </div>

            <div className="mt-14 grid gap-6 lg:grid-cols-3">
              {/* POS */}
              <div className="group overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-rasa-500/10">
                <div className="relative h-48 overflow-hidden">
                  <Image
                    src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=900&q=80"
                    alt="Cashier processing payment on a POS machine"
                    fill
                    sizes="(max-width: 1024px) 100vw, 33vw"
                    className="object-cover transition duration-500 group-hover:scale-105"
                  />
                  <div className="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/50 to-transparent" />
                  <span className="absolute bottom-3 left-4 rounded-full bg-white/90 px-3 py-1 text-[11px] font-black text-rasa-600 backdrop-blur">
                    POS · Dine-in & takeaway
                  </span>
                </div>
                <div className="p-6">
                  <h3 className="text-xl font-black text-ink">A till that keeps up with rush hour</h3>
                  <p className="mt-2 text-sm leading-relaxed text-stone-600">
                    Add items, pick a table and take payment in a few taps. Works great on touchscreens, tablets and laptops.
                  </p>
                  <ul className="mt-5 space-y-2.5 text-sm font-medium text-stone-700">
                    {["Auto table sessions — open and close", "Cash, card, QR & e-wallet payment records", "Receipts print right from the browser"].map((t) => (
                      <li key={t} className="flex items-start gap-2">
                        <Check className="mt-0.5 h-4 w-4" />
                        {t}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>

              {/* Staff ordering */}
              <div className="group overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-rasa-500/10">
                <div className="relative h-48 overflow-hidden">
                  <Image
                    src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&w=900&q=80"
                    alt="Staff taking an order at a busy cafe counter"
                    fill
                    sizes="(max-width: 1024px) 100vw, 33vw"
                    className="object-cover transition duration-500 group-hover:scale-105"
                  />
                  <div className="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/50 to-transparent" />
                  <span className="absolute bottom-3 left-4 rounded-full bg-white/90 px-3 py-1 text-[11px] font-black text-ink backdrop-blur">
                    Staff ordering
                  </span>
                </div>
                <div className="p-6">
                  <h3 className="text-xl font-black text-ink">Order from anywhere on the floor</h3>
                  <p className="mt-2 text-sm leading-relaxed text-stone-600">
                    Waiters send orders straight to the kitchen from their own phone or tablet. No more shouting or lost notes.
                  </p>
                  <ul className="mt-5 space-y-2.5 text-sm font-medium text-stone-700">
                    {["Big touch-friendly menu & categories", "Live order status for the kitchen", "Special notes — less spicy, no ice"].map((t) => (
                      <li key={t} className="flex items-start gap-2">
                        <Check className="mt-0.5 h-4 w-4" />
                        {t}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>

              {/* Customer QR */}
              <div className="group overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-rasa-500/10">
                <div className="relative h-48 overflow-hidden">
                  <Image
                    src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=900&q=80"
                    alt="Guests scanning a table QR to order at a restaurant"
                    fill
                    sizes="(max-width: 1024px) 100vw, 33vw"
                    className="object-cover transition duration-500 group-hover:scale-105"
                  />
                  <div className="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/50 to-transparent" />
                  <span className="absolute bottom-3 left-4 rounded-full bg-white/90 px-3 py-1 text-[11px] font-black text-emerald-600 backdrop-blur">
                    Customer QR ordering · Premium+
                  </span>
                </div>
                <div className="p-6">
                  <h3 className="text-xl font-black text-ink">Customers scan, order and relax</h3>
                  <p className="mt-2 text-sm leading-relaxed text-stone-600">
                    A QR card on the table opens your menu in the customer&apos;s phone. No app, no account — just order.
                  </p>
                  <ul className="mt-5 space-y-2.5 text-sm font-medium text-stone-700">
                    {["Mobile menu with RM prices", "Orders land straight in your kitchen", "Easier on your staff during peak hours"].map((t) => (
                      <li key={t} className="flex items-start gap-2">
                        <Check className="mt-0.5 h-4 w-4" />
                        {t}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ================= HOW IT WORKS ================= */}
        <section id="how" className="scroll-mt-20 bg-[#fdf8f6] py-20">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-black uppercase tracking-[0.25em] text-rasa-500">How it works</p>
              <h2 className="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Up and running in three steps</h2>
            </div>
            <div className="mt-14 grid gap-6 md:grid-cols-3">
              {[
                ["01", "Set up", "Add your menu, tables and staff. Malaysian defaults are ready — RM and Asia/Kuala_Lumpur.", "🍳"],
                ["02", "Take orders", "Use the till, let staff order from tablets, or let customers scan a table QR.", "📲"],
                ["03", "Get paid", "Cash, card or QR. Receipt prints, session closes and sales are recorded automatically.", "💰"],
              ].map(([n, t, d, e]) => (
                <div key={n} className="rounded-3xl border border-stone-200 bg-white p-7 shadow-sm">
                  <div className="flex items-center justify-between">
                    <span className="text-4xl font-black text-rasa-100">{n}</span>
                    <span className="text-3xl">{e}</span>
                  </div>
                  <h3 className="mt-4 text-lg font-black text-ink">{t}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-stone-600">{d}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ================= FEATURES ================= */}
        <section id="features" className="scroll-mt-20 bg-white py-20">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-black uppercase tracking-[0.25em] text-rasa-500">Features</p>
              <h2 className="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Simple, Malaysian, complete</h2>
            </div>
            <div className="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
              {[
                ["🧑‍🍳", "Staff & roles", "Owner, manager and staff — the right access for each role."],
                ["🪑", "Table management", "See available, occupied and open sessions at a glance."],
                ["📊", "Sales & money", "Daily sales, discounts and payment breakdowns, kept simple."],
                ["📈", "Reports", "Daily, weekly and monthly — by product, category or payment."],
                ["🧾", "Receipts", "Clean browser receipts with your restaurant name and logo."],
                ["🔒", "Tenant-safe data", "Every restaurant fully isolated. Your data stays yours."],
              ].map(([e, t, d]) => (
                <div key={t as string} className="flex gap-4">
                  <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-rasa-50 text-xl ring-1 ring-rasa-100">{e}</span>
                  <div>
                    <h3 className="font-black text-ink">{t}</h3>
                    <p className="mt-1 text-sm leading-relaxed text-stone-600">{d}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ================= PRICING ================= */}
        <section id="pricing" className="scroll-mt-20 bg-[#fdf8f6] py-20 sm:py-24">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="mx-auto max-w-2xl text-center">
              <p className="text-xs font-black uppercase tracking-[0.25em] text-rasa-500">Pricing</p>
              <h2 className="mt-3 text-3xl font-black tracking-tight text-ink sm:text-4xl">Pick the plan that fits your shop</h2>
              <p className="mt-4 text-stone-600">Every plan starts with a 14-day free trial. Upgrade or cancel anytime.</p>
            </div>
            <PricingCards />
          </div>
        </section>

        {/* ================= CTA ================= */}
        <section className="bg-white py-20">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <div className="relative overflow-hidden rounded-[2.5rem] bg-rasa-500 px-6 py-16 text-center text-white shadow-2xl shadow-rasa-500/30 sm:px-12">
              <div className="pointer-events-none absolute -left-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl" />
              <div className="pointer-events-none absolute -bottom-20 -right-10 h-72 w-72 rounded-full bg-rasa-900/40 blur-3xl" />
              <div className="relative">
                <p className="text-3xl">☕</p>
                <h2 className="mx-auto mt-4 max-w-2xl text-3xl font-black leading-tight tracking-tight sm:text-4xl">Ready for a smoother shift?</h2>
                <p className="mx-auto mt-3 max-w-xl text-rasa-100">See your first order in 15 minutes — no credit card, no commitment.</p>
                <div className="mt-8 flex flex-wrap items-center justify-center gap-3.5">
                  <Link
                    href="/register"
                    className="rounded-2xl bg-white px-8 py-4 text-base font-black text-rasa-600 shadow-xl transition hover:-translate-y-0.5 hover:bg-rasa-50"
                  >
                    Start 14-day free trial
                  </Link>
                  <a href="#pricing" className="rounded-2xl border border-white/40 px-8 py-4 text-base font-bold text-white transition hover:bg-white/10">
                    See pricing
                  </a>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      {/* ================= FOOTER ================= */}
      <footer className="border-t border-stone-200 bg-[#fdf8f6]">
        <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
          <div className="flex flex-col items-center justify-between gap-10 md:flex-row md:items-start">
            <div className="max-w-xs text-center md:text-left">
              <Logo />
              <p className="mt-3 text-sm leading-relaxed text-stone-500">
                Simple POS. Simple Ordering. Simple Business — for Malaysian restaurants, cafes and warungs.
              </p>
            </div>
            <div className="flex gap-16 text-sm">
              <div>
                <p className="font-black text-ink">Product</p>
                <ul className="mt-3 space-y-2.5 text-stone-500">
                  <li><a href="#product" className="transition hover:text-rasa-600">POS</a></li>
                  <li><a href="#product" className="transition hover:text-rasa-600">Staff ordering</a></li>
                  <li><a href="#product" className="transition hover:text-rasa-600">Customer QR</a></li>
                  <li><a href="#pricing" className="transition hover:text-rasa-600">Pricing</a></li>
                </ul>
              </div>
              <div>
                <p className="font-black text-ink">Account</p>
                <ul className="mt-3 space-y-2.5 text-stone-500">
                  <li><Link href="/login" className="transition hover:text-rasa-600">Log in</Link></li>
                  <li><Link href="/register" className="transition hover:text-rasa-600">Start free trial</Link></li>
                </ul>
              </div>
            </div>
          </div>
          <div className="mt-10 flex flex-col items-center justify-between gap-3 border-t border-stone-200 pt-6 text-xs text-stone-400 sm:flex-row">
            <p>© {new Date().getFullYear()} Sajio.my · Simple POS. Simple Ordering. Simple Business.</p>
            <p>Made with ☕ in Malaysia 🇲🇾</p>
          </div>
        </div>
      </footer>
    </>
  );
}
