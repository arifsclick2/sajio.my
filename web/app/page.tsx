import Link from "next/link";

const features = [
  {
    icon: "🍽️",
    title: "Fast POS",
    description:
      "Dine-in, takeaway and table service with minimal taps. Open a bill, add items, take payment and close the session.",
  },
  {
    icon: "📱",
    title: "Staff Ordering",
    description:
      "Staff take orders from a phone or tablet. Select or scan a table, send the order straight to the kitchen.",
  },
  {
    icon: "🔗",
    title: "Table QR Ordering",
    description:
      "Customers scan a QR at the table to browse the menu, build a cart and place their own order. No app needed.",
  },
  {
    icon: "🏷️",
    title: "Table Tags (Pro)",
    description:
      "Print table cards with QR + NFC-ready tags. Cashiers scan the tag to pull up the table's bill instantly.",
  },
  {
    icon: "🧾",
    title: "Sales & Expenses",
    description:
      "Track sales, payment types, expenses and everyday money. A simple business money summary, not a full ERP.",
  },
  {
    icon: "📊",
    title: "Reports",
    description:
      "Daily, weekly and monthly sales. Product, category, payment and expense summaries that are always tenant-safe.",
  },
];

const steps = [
  {
    step: "01",
    title: "Set up your restaurant",
    description:
      "Add your menu, tables and staff in minutes. Malaysian defaults: MYR, Asia/Kuala_Lumpur.",
  },
  {
    step: "02",
    title: "Start taking orders",
    description:
      "Use the POS, let staff order from tablets, or let customers scan and order from the table.",
  },
  {
    step: "03",
    title: "Get paid & stay on top",
    description:
      "Cash, card or QR. Receipts print instantly, sessions close and your sales are recorded automatically.",
  },
];

const plans = [
  {
    name: "Basic",
    blurb: "For small shops getting started",
    highlights: ["POS + table ordering", "5 staff & 1 POS device", "Up to 10 tables & 100 items", "Sales, expenses & reports"],
  },
  {
    name: "Premium",
    blurb: "For busy cafes & restaurants",
    highlights: ["Everything in Basic", "Customer QR ordering", "3 POS devices", "Advanced reports"],
    featured: true,
  },
  {
    name: "Pro",
    blurb: "For serious F&B operations",
    highlights: ["Everything in Premium", "Table Card / Tag system", "Fast table scan at POS", "NFC-ready backend"],
  },
];

function Logo() {
  return (
    <span className="text-xl font-bold tracking-tight">
      sajio<span className="text-indigo-400">.</span>
    </span>
  );
}

export default function HomePage() {
  return (
    <>
      {/* Nav */}
      <header className="sticky top-0 z-40 border-b border-white/5 bg-[#090a0f]/80 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
          <Link href="/" aria-label="Sajio home">
            <Logo />
          </Link>
          <nav className="hidden items-center gap-8 text-sm text-gray-300 md:flex">
            <a className="transition hover:text-white" href="#features">Features</a>
            <a className="transition hover:text-white" href="#how">How it works</a>
            <a className="transition hover:text-white" href="#pricing">Packages</a>
          </nav>
          <div className="flex items-center gap-3">
            <Link
              href="/login"
              className="rounded-lg px-4 py-2 text-sm font-medium text-gray-200 transition hover:text-white"
            >
              Log in
            </Link>
            <Link
              href="/register"
              className="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400"
            >
              Start free trial
            </Link>
          </div>
        </div>
      </header>

      <main className="flex-1">
        {/* Hero */}
        <section className="relative overflow-hidden">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_-10%,rgba(99,102,241,0.15),transparent_60%)]" />
          <div className="relative mx-auto max-w-6xl px-4 pb-20 pt-24 text-center">
            <p className="mx-auto mb-6 inline-block rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-xs font-medium tracking-wide text-indigo-200">
              Built for Malaysian restaurants, cafes &amp; warungs 🇲🇾
            </p>
            <h1 className="mx-auto max-w-3xl text-4xl font-bold leading-tight sm:text-6xl">
              Simple POS.{" "}
              <span className="bg-gradient-to-r from-white via-indigo-200 to-indigo-400 bg-clip-text text-transparent">
                Simple Ordering.
              </span>{" "}
              Simple Business.
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-lg text-gray-400">
              One simple system to manage tables, orders, payments, sales and
              everyday money. Your staff will learn it in minutes — not days.
            </p>
            <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
              <Link
                href="/register"
                className="rounded-xl bg-indigo-500 px-7 py-3.5 text-base font-semibold text-white shadow-xl shadow-indigo-500/30 transition hover:bg-indigo-400"
              >
                Start your 14-day free trial
              </Link>
              <a
                href="#how"
                className="rounded-xl border border-white/10 bg-white/5 px-7 py-3.5 text-base font-medium text-gray-200 transition hover:bg-white/10"
              >
                See how it works
              </a>
            </div>
            <p className="mt-6 text-sm text-gray-500">
              No credit card required · MYR · Free trial · Cancel anytime
            </p>
          </div>
        </section>

        {/* Features */}
        <section id="features" className="border-t border-white/5 py-20">
          <div className="mx-auto max-w-6xl px-4">
            <h2 className="text-center text-3xl font-bold sm:text-4xl">
              Everything a restaurant needs. Nothing it doesn&apos;t.
            </h2>
            <p className="mx-auto mt-4 max-w-2xl text-center text-gray-400">
              From the first order to the end-of-day report — Sajio keeps your
              restaurant running smoothly without ERP-style complexity.
            </p>
            <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {features.map((f) => (
                <div
                  key={f.title}
                  className="rounded-2xl border border-white/8 bg-white/[0.02] p-6 transition hover:border-indigo-500/40 hover:bg-white/[0.04]"
                >
                  <div className="mb-4 text-3xl">{f.icon}</div>
                  <h3 className="text-lg font-semibold">{f.title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-gray-400">
                    {f.description}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* How it works */}
        <section id="how" className="border-t border-white/5 py-20">
          <div className="mx-auto max-w-6xl px-4">
            <h2 className="text-center text-3xl font-bold sm:text-4xl">
              From scan to receipt in three steps
            </h2>
            <div className="mt-14 grid gap-8 md:grid-cols-3">
              {steps.map((s) => (
                <div key={s.step} className="relative rounded-2xl border border-white/8 bg-white/[0.02] p-6">
                  <span className="text-sm font-bold text-indigo-400">{s.step}</span>
                  <h3 className="mt-3 text-xl font-semibold">{s.title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-gray-400">{s.description}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Pricing */}
        <section id="pricing" className="border-t border-white/5 py-20">
          <div className="mx-auto max-w-6xl px-4">
            <h2 className="text-center text-3xl font-bold sm:text-4xl">Packages</h2>
            <p className="mx-auto mt-4 max-w-2xl text-center text-gray-400">
              Start free for 14 days. Pick a package when you&apos;re ready —
              every plan includes table management, POS and reports.
            </p>
            <div className="mt-14 grid gap-6 md:grid-cols-3">
              {plans.map((p) => (
                <div
                  key={p.name}
                  className={`relative rounded-2xl border p-7 ${
                    p.featured
                      ? "border-indigo-500/50 bg-indigo-500/[0.06] shadow-2xl shadow-indigo-500/10"
                      : "border-white/8 bg-white/[0.02]"
                  }`}
                >
                  {p.featured && (
                    <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-indigo-500 px-3 py-1 text-xs font-semibold text-white">
                      Most popular
                    </span>
                  )}
                  <h3 className="text-lg font-bold">{p.name}</h3>
                  <p className="mt-1 text-sm text-gray-400">{p.blurb}</p>
                  <ul className="mt-6 space-y-3 text-sm">
                    {p.highlights.map((h) => (
                      <li key={h} className="flex items-start gap-2.5">
                        <svg className="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                          <path
                            fillRule="evenodd"
                            d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                            clipRule="evenodd"
                          />
                        </svg>
                        {h}
                      </li>
                    ))}
                  </ul>
                  <Link
                    href="/register"
                    className={`mt-8 block rounded-xl px-5 py-3 text-center text-sm font-semibold transition ${
                      p.featured
                        ? "bg-indigo-500 text-white hover:bg-indigo-400"
                        : "border border-white/10 bg-white/5 text-gray-100 hover:bg-white/10"
                    }`}
                  >
                    Start free trial
                  </Link>
                </div>
              ))}
            </div>
            <p className="mt-8 text-center text-xs text-gray-500">
              Package pricing details are announced soon. All packages include a
              14-day free trial.
            </p>
          </div>
        </section>

        {/* CTA */}
        <section className="border-t border-white/5 py-20">
          <div className="mx-auto max-w-3xl px-4 text-center">
            <h2 className="text-3xl font-bold sm:text-4xl">
              Ready to run your restaurant simpler?
            </h2>
            <p className="mt-4 text-gray-400">
              Set up your menu, tables and staff today. See your first order in
              under 15 minutes.
            </p>
            <Link
              href="/register"
              className="mt-8 inline-block rounded-xl bg-indigo-500 px-8 py-4 text-base font-semibold text-white shadow-xl shadow-indigo-500/30 transition hover:bg-indigo-400"
            >
              Create your restaurant — free for 14 days
            </Link>
          </div>
        </section>
      </main>

      {/* Footer */}
      <footer className="border-t border-white/5 py-10">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 sm:flex-row">
          <Logo />
          <p className="text-sm text-gray-500">
            © {new Date().getFullYear()} Sajio.my · Simple POS. Simple Ordering. Simple Business.
          </p>
        </div>
      </footer>
    </>
  );
}
