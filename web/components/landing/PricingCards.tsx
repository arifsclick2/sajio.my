"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api } from "../../lib/api";

interface Pkg {
  id: number;
  name: string;
  slug: string;
  price_monthly: string;
}

/** Live prices come from GET /api/v1/billing/packages; these are the same
 *  published defaults used while the request is in flight (or offline). */
const FALLBACK: Pkg[] = [
  { id: 1, name: "Basic", slug: "basic", price_monthly: "299" },
  { id: 2, name: "Premium", slug: "premium", price_monthly: "499" },
  { id: 3, name: "Pro", slug: "pro", price_monthly: "999" },
];

const FEATURES: Record<string, string[]> = {
  basic: [
    "1 POS device · up to 5 staff",
    "Up to 10 tables · 100 menu items",
    "Dine-in & takeaway orders",
    "Sales & daily reports",
  ],
  premium: [
    "Everything in Basic",
    "Customer QR ordering",
    "3 POS devices · up to 30 tables",
    "Advanced reports & staff sales",
  ],
  pro: [
    "Everything in Premium",
    "Table Tag / QR-NFC card system",
    "Instant table scan at the till",
    "Print branded table cards",
  ],
};

function Check({ light = false }: { light?: boolean }) {
  return (
    <svg className={`h-5 w-5 shrink-0 ${light ? "text-rasa-300" : "text-rasa-600"}`} viewBox="0 0 20 20" fill="currentColor">
      <path
        fillRule="evenodd"
        d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z"
        clipRule="evenodd"
      />
    </svg>
  );
}

export default function PricingCards() {
  const [packages, setPackages] = useState<Pkg[] | null>(null);

  useEffect(() => {
    let active = true;
    // Fetch the published package prices once; keep static prices otherwise.
    api
      .packages()
      .then((r) => {
        if (active) {
          setPackages(r.packages.map((p) => ({ id: p.id, name: p.name, slug: p.slug, price_monthly: p.price_monthly })));
        }
      })
      .catch(() => {
        /* offline / not ready — fallback prices below */
      });
    return () => {
      active = false;
    };
  }, []);

  const list = packages ?? FALLBACK;
  const popular = "premium";

  return (
    <div className="mt-14 grid gap-6 md:grid-cols-3">
      {list.map((p) => {
        const isPopular = p.slug === popular;
        const feats = FEATURES[p.slug] ?? FEATURES.basic;
        return (
          <div
            key={p.slug}
            className={`relative flex flex-col rounded-3xl p-8 ${
              isPopular
                ? "border-2 border-rasa-500 bg-white shadow-2xl shadow-rasa-500/20"
                : "border border-stone-200 bg-white shadow-sm"
            }`}
          >
            {isPopular && (
              <span className="absolute -top-3.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-rasa-500 px-4 py-1 text-xs font-black text-white shadow-md">
                MOST POPULAR
              </span>
            )}
            <div className="flex items-baseline gap-1.5">
              <h3 className={`text-xl font-black ${isPopular ? "text-rasa-600" : "text-ink"}`}>{p.name}</h3>
              <span className="text-xs font-semibold text-stone-400">/ month</span>
            </div>
            <p className="mt-3 flex items-baseline gap-1">
              <span className={`text-[13px] font-bold ${isPopular ? "text-stone-500" : "text-stone-500"}`}>RM</span>
              <span className={`text-5xl font-black tracking-tight ${isPopular ? "text-rasa-600" : "text-ink"}`}>
                {Number(p.price_monthly).toFixed(0)}
              </span>
            </p>
            <p className="mt-1 text-xs font-semibold text-emerald-600">14-day free trial · no credit card</p>
            <ul className={`mt-6 flex-1 space-y-3 text-sm text-stone-700`}>
              {feats.map((f) => (
                <li key={f} className="flex items-start gap-2.5">
                  <Check light={false} />
                  {f}
                </li>
              ))}
            </ul>
            <Link
              href="/register"
              className={`mt-8 block rounded-2xl px-5 py-3.5 text-center text-sm font-black transition hover:-translate-y-0.5 ${
                isPopular ? "bg-rasa-500 text-white shadow-lg shadow-rasa-500/30 hover:bg-rasa-600" : "bg-ink text-white hover:bg-stone-800"
              }`}
            >
              Start free trial
            </Link>
          </div>
        );
      })}
    </div>
  );
}
