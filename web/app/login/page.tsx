"use client";

import Link from "next/link";
import { useState } from "react";
import { api } from "../../lib/api";
import { saveSession } from "../../lib/session";

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const { token, user, restaurant } = await api.login({ email, password });
      saveSession(token, user, restaurant);
      const app = process.env.NEXT_PUBLIC_APP_URL ?? "https://app.sajio.my";
      // Cross-host redirect (sajio.my -> app.sajio.my) — router.push can't do this.
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination
      window.location.assign(`${app}/dashboard`);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to log in.");
    } finally {
      setLoading(false);
    }
  }

  const inputCls =
    "w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-ink placeholder-stone-400 outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100";

  return (
    <main className="relative flex flex-1 items-center justify-center overflow-hidden bg-[#fdf8f6] px-4 py-16">
      <div className="pointer-events-none absolute -top-24 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-rasa-100/70 blur-3xl" />
      <div className="relative w-full max-w-md rounded-3xl border border-stone-200 bg-white p-8 shadow-2xl shadow-rasa-900/10">
        <Link href="/" className="mb-7 block text-center text-xl font-extrabold tracking-tight text-ink">
          <span className="mr-1.5 inline-grid h-8 w-8 place-items-center rounded-xl bg-rasa-500 align-middle text-sm font-black text-white">S</span>
          sajio<span className="text-rasa-500">.</span>
        </Link>
        <h1 className="text-center text-2xl font-black tracking-tight text-ink">Welcome back 👋</h1>
        <p className="mt-2 text-center text-sm text-stone-500">Log in to your restaurant.</p>

        {error && <p className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">{error}</p>}

        <form onSubmit={handleSubmit} className="mt-8 space-y-5">
          <div>
            <label htmlFor="email" className="mb-1.5 block text-sm font-bold text-stone-700">Email</label>
            <input id="email" type="email" required autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} className={inputCls} placeholder="you@restaurant.my" />
          </div>
          <div>
            <label htmlFor="password" className="mb-1.5 block text-sm font-bold text-stone-700">Password</label>
            <input id="password" type="password" required autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} className={inputCls} placeholder="••••••••" />
          </div>
          <button type="submit" disabled={loading} className="w-full rounded-xl bg-rasa-500 px-5 py-3 text-sm font-black text-white shadow-lg shadow-rasa-500/25 transition hover:bg-rasa-600 disabled:cursor-not-allowed disabled:opacity-60">
            {loading ? "Logging in…" : "Log in"}
          </button>
        </form>

        <p className="mt-8 text-center text-sm text-stone-500">
          New to Sajio?{" "}
          <Link href="/register" className="font-black text-rasa-600 transition hover:text-rasa-700">Start 14-day free trial</Link>
        </p>
      </div>
    </main>
  );
}
