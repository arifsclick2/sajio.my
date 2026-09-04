export const API_URL =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export interface User {
  id: number;
  name: string;
  email: string;
  created_at: string;
  updated_at: string;
}

export interface AuthResponse {
  user: User;
  token: string;
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(options.headers ?? {}),
    },
    cache: "no-store",
  });

  if (!res.ok) {
    let message = `Request failed (${res.status})`;
    try {
      const body = await res.json();
      if (typeof body.message === "string") message = body.message;
      if (body.errors) {
        const first = Object.values(body.errors)[0] as string[] | undefined;
        if (first?.[0]) message = first[0];
      }
    } catch {
      // response was not JSON; keep the generic message
    }
    throw new Error(message);
  }

  return res.json() as Promise<T>;
}

export const api = {
  register(data: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) {
    return request<AuthResponse>("/api/v1/auth/register", {
      method: "POST",
      body: JSON.stringify(data),
    });
  },

  login(data: { email: string; password: string }) {
    return request<AuthResponse>("/api/v1/auth/login", {
      method: "POST",
      body: JSON.stringify(data),
    });
  },

  me(token: string) {
    return request<User>("/api/v1/auth/me", {
      headers: { Authorization: `Bearer ${token}` },
    });
  },

  logout(token: string) {
    return request<{ message: string }>("/api/v1/auth/logout", {
      method: "POST",
      headers: { Authorization: `Bearer ${token}` },
    });
  },
};
