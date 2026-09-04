"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "../../lib/api";
import { saveSession } from "../../lib/session";

export default function RegisterPage() {
  const router = useRouter();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    if (password !== passwordConfirmation) {
      setError("Kata laluan tidak sama. Sila cuba lagi.");
      return;
    }
    setLoading(true);
    try {
      const { token, user } = await api.register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      saveSession(token, user);
      router.push("/");
      router.refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to create your account.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="pattern-batik relative flex flex-1 items-center justify-center overflow-hidden px-4 py-16">
      <div className="pointer-events-none absolute -top-24 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-gold-300/30 blur-3xl" />
      <div className="relative w-full max-w-md rounded-3xl border border-stone-200 bg-white p-8 shadow-2xl shadow-brand-900/10">
        <Link href="/" className="mb-7 block text-center text-xl font-extrabold tracking-tight text-ink">
          <span className="mr-1.5 inline-grid h-8 w-8 place-items-center rounded-xl bg-gradient-to-br from-brand-600 to-emerald-500 align-middle text-sm font-black text-white">
            S
          </span>
          sajio<span className="text-brand-600">.</span>
        </Link>
        <h1 className="text-center text-2xl font-black tracking-tight text-ink">Cipta restoran anda</h1>
        <p className="mt-2 text-center text-sm text-stone-500">14 hari percuma · Tiada kad kredit diperlukan</p>

        {error && (
          <p className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">{error}</p>
        )}

        <form onSubmit={handleSubmit} className="mt-8 space-y-5">
          <div>
            <label htmlFor="name" className="mb-1.5 block text-sm font-bold text-stone-700">
              Nama restoran / pemilik
            </label>
            <input
              id="name"
              type="text"
              required
              autoComplete="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-ink placeholder-stone-400 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
              placeholder="Kedai Kopi Sajio"
            />
          </div>
          <div>
            <label htmlFor="email" className="mb-1.5 block text-sm font-bold text-stone-700">
              Email
            </label>
            <input
              id="email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-ink placeholder-stone-400 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
              placeholder="anda@restoran.my"
            />
          </div>
          <div>
            <label htmlFor="password" className="mb-1.5 block text-sm font-bold text-stone-700">
              Kata laluan
            </label>
            <input
              id="password"
              type="password"
              required
              autoComplete="new-password"
              minLength={8}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-ink placeholder-stone-400 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
              placeholder="Sekurang-kurangnya 8 aksara"
            />
          </div>
          <div>
            <label htmlFor="password_confirmation" className="mb-1.5 block text-sm font-bold text-stone-700">
              Sahkan kata laluan
            </label>
            <input
              id="password_confirmation"
              type="password"
              required
              autoComplete="new-password"
              minLength={8}
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              className="w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-ink placeholder-stone-400 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100"
              placeholder="••••••••"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-black text-white shadow-lg shadow-brand-700/25 transition hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {loading ? "Mencipta akaun…" : "Mula 14 hari percuma"}
          </button>
        </form>

        <p className="mt-6 text-center text-xs leading-relaxed text-stone-400">
          Onboarding restoran (menu, meja, staff) akan dibuka dalam fasa seterusnya.
        </p>

        <p className="mt-5 text-center text-sm text-stone-500">
          Sudah ada akaun?{" "}
          <Link href="/login" className="font-black text-brand-700 transition hover:text-brand-800">
            Log masuk
          </Link>
        </p>
      </div>
    </main>
  );
}
