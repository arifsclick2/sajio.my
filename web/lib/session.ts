import type { Restaurant, User } from "./api";

const TOKEN_KEY = "sajio_token";
const USER_KEY = "sajio_user";
const RESTAURANT_KEY = "sajio_restaurant";

function safeParse<T>(raw: string | null): T | null {
  if (!raw) return null;
  try {
    return JSON.parse(raw) as T;
  } catch {
    return null;
  }
}

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function getStoredUser(): User | null {
  if (typeof window === "undefined") return null;
  return safeParse<User>(window.localStorage.getItem(USER_KEY));
}

export function getStoredRestaurant(): Restaurant | null {
  if (typeof window === "undefined") return null;
  return safeParse<Restaurant>(window.localStorage.getItem(RESTAURANT_KEY));
}

export function saveSession(token: string, user: User, restaurant?: Restaurant | null) {
  window.localStorage.setItem(TOKEN_KEY, token);
  window.localStorage.setItem(USER_KEY, JSON.stringify(user));
  if (restaurant) {
    window.localStorage.setItem(RESTAURANT_KEY, JSON.stringify(restaurant));
  }
}

export function clearSession() {
  window.localStorage.removeItem(TOKEN_KEY);
  window.localStorage.removeItem(USER_KEY);
  window.localStorage.removeItem(RESTAURANT_KEY);
}

/** Dashboard lives on the app subdomain. */
export function dashboardUrl(path = "/dashboard"): string {
  const app = process.env.NEXT_PUBLIC_APP_URL ?? "https://app.sajio.my";
  return `${app}${path}`;
}
