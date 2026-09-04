"use client";

import Link from "next/link";
import { useState } from "react";
import { api } from "../../lib/api";
import { saveSession } from "../../lib/session";

export default function RegisterPage() {
  // Step 1: account + restaurant
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [restaurantName, setRestaurantName] = useState("");
  const [subdomain, setSubdomain] = useState("");
  const [couponCode, setCouponCode] = useState("");
  const [subAvailable, setSubAvailable] = useState<boolean | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  // Step 2: OTP
  const [registeredEmail, setRegisteredEmail] = useState<string | null>(null);
  const [otp, setOtp] = useState("");
  const [otpLoading, setOtpLoading] = useState(false);
  const [resendMsg, setResendMsg] = useState<string | null>(null);

  async function checkSubdomain(value: string) {
    setSubdomain(value);
    const v = value.trim().toLowerCase();
    if (v.length < 3) {
      setSubAvailable(null);
      return;
    }
    try {
      const r = await api.checkSubdomain(v);
      setSubAvailable(r.available);
    } catch {
      setSubAvailable(false);
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    if (password !== passwordConfirmation) {
      setError("Passwords do not match. Please try again.");
      return;
    }
    setLoading(true);
    try {
      await api.registerV2({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        restaurant_name: restaurantName,
        subdomain: subdomain.trim().toLowerCase(),
        coupon_code: couponCode.trim() || undefined,
      });
      setRegisteredEmail(email);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to register.");
    } finally {
      setLoading(false);
    }
  }

  async function handleVerify(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setOtpLoading(true);
    try {
      const res = await api.verifyOtp(registeredEmail!, otp.trim());
      saveSession(res.token, res.user, res.restaurant);
      // Dashboard lives on app.sajio.my
      window.location.href = (process.env.NEXT_PUBLIC_APP_URL ?? "https://app.sajio.my") + "/dashboard";
    } catch (err) {
      setError(err instanceof Error ? err.message : "Invalid code.");
    } finally {
      setOtpLoading(false);
    }
  }

  async function handleResend() {
    setResendMsg(null);
    try {
      const r = await api.resendOtp(registeredEmail!);
      setResendMsg(r.message ?? "Code sent.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to resend.");
    }
  }

  const inputCls =
    "w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-ink placeholder-stone-400 outline-none transition focus:border-rasa-500 focus:ring-4 focus:ring-rasa-100";
  const logoBtnCls =
    "w-full rounded-xl bg-rasa-500 px-5 py-3 text-sm font-black text-white shadow-lg shadow-rasa-500/25 transition hover:bg-rasa-600 disabled:cursor-not-allowed disabled:opacity-60";

  if (registeredEmail) {
    return (
      <main className="relative flex flex-1 items-center justify-center overflow-hidden bg-[#fdf8f6] px-4 py-16">
        <div className="pointer-events-none absolute -top-24 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-rasa-100/70 blur-3xl" />
        <div className="relative w-full max-w-md rounded-3xl border border-stone-200 bg-white p-8 shadow-2xl shadow-rasa-900/10">
          <Link href="/" className="mb-7 block text-center text-xl font-extrabold tracking-tight text-ink">
            <span className="mr-1.5 inline-grid h-8 w-8 place-items-center rounded-xl bg-rasa-500 align-middle text-sm font-black text-white">S</span>
            sajio<span className="text-rasa-500">.</span>
          </Link>
          <h1 className="text-center text-2xl font-black tracking-tight text-ink">Check your email 📩</h1>
          <p className="mt-2 text-center text-sm text-stone-500">
            We sent a 6-digit code to <span className="font-bold text-ink">{registeredEmail}</span>. It expires in 10 minutes.
          </p>

          {error && <p className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">{error}</p>}
          {resendMsg && <p className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{resendMsg}</p>}

          <form onSubmit={handleVerify} className="mt-8 space-y-5">
            <div>
              <label htmlFor="otp" className="mb-1.5 block text-sm font-bold text-stone-700">Verification code</label>
              <input
                id="otp"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                required
                value={otp}
                onChange={(e) => setOtp(e.target.value)}
                className={inputCls}
                placeholder="••••••"
              />
            </div>
            <button type="submit" disabled={otpLoading} className={logoBtnCls}>
              {otpLoading ? "Verifying…" : "Verify & start your 14-day free trial"}
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-stone-500">
            Didn&apos;t receive it?{" "}
            <button type="button" onClick={handleResend} className="font-black text-rasa-600 transition hover:text-rasa-700">
              Resend code
            </button>
          </p>
        </div>
      </main>
    );
  }

  return (
    <main className="relative flex flex-1 items-center justify-center overflow-hidden bg-[#fdf8f6] px-4 py-16">
      <div className="pointer-events-none absolute -top-24 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-rasa-100/70 blur-3xl" />
      <div className="relative w-full max-w-lg rounded-3xl border border-stone-200 bg-white p-8 shadow-2xl shadow-rasa-900/10">
        <Link href="/" className="mb-7 block text-center text-xl font-extrabold tracking-tight text-ink">
          <span className="mr-1.5 inline-grid h-8 w-8 place-items-center rounded-xl bg-rasa-500 align-middle text-sm font-black text-white">S</span>
          sajio<span className="text-rasa-500">.</span>
        </Link>
        <h1 className="text-center text-2xl font-black tracking-tight text-ink">Create your restaurant</h1>
        <p className="mt-2 text-center text-sm text-stone-500">14-day free trial · No credit card needed</p>

        {error && <p className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">{error}</p>}

        <form onSubmit={handleSubmit} className="mt-8 space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-bold text-stone-700">Your name</label>
              <input required value={name} onChange={(e) => setName(e.target.value)} className={inputCls} placeholder="Full name" />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-bold text-stone-700">Restaurant name</label>
              <input required value={restaurantName} onChange={(e) => setRestaurantName(e.target.value)} className={inputCls} placeholder="Kedai Kopi Sajio" />
            </div>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-bold text-stone-700">Email</label>
            <input type="email" required autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} className={inputCls} placeholder="you@restaurant.my" />
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-bold text-stone-700">Restaurant subdomain</label>
            <div className="flex items-center rounded-xl border border-stone-300 bg-white px-4 focus-within:border-rasa-500 focus-within:ring-4 focus-within:ring-rasa-100">
              <input required value={subdomain} onChange={(e) => checkSubdomain(e.target.value)} className="w-full bg-transparent py-2.5 text-sm text-ink placeholder-stone-400 outline-none" placeholder="your-kopitiam" />
              <span className="shrink-0 text-sm font-medium text-stone-400">.sajio.my</span>
            </div>
            {subAvailable === true && <p className="mt-1 text-xs font-semibold text-emerald-600">✓ Subdomain is available</p>}
            {subAvailable === false && <p className="mt-1 text-xs font-semibold text-rasa-500">✕ Subdomain is taken or invalid</p>}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-bold text-stone-700">Password</label>
              <input type="password" required autoComplete="new-password" minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} className={inputCls} placeholder="Min 8 characters" />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-bold text-stone-700">Confirm password</label>
              <input type="password" required autoComplete="new-password" minLength={8} value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} className={inputCls} placeholder="••••••••" />
            </div>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-bold text-stone-700">Promo code <span className="font-normal text-stone-400">(optional)</span></label>
            <input value={couponCode} onChange={(e) => setCouponCode(e.target.value)} className={inputCls} placeholder="SAJIO10" />
          </div>

          <button type="submit" disabled={loading} className={logoBtnCls}>
            {loading ? "Creating account…" : "Create account & get your OTP"}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-stone-500">
          Already have an account?{" "}
          <Link href="/login" className="font-black text-rasa-600 transition hover:text-rasa-700">Log in</Link>
        </p>
      </div>
    </main>
  );
}
