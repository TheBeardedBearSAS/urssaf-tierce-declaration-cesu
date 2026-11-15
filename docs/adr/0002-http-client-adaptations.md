# ADR 0002 — HTTP client adaptations to the CESU contract

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The reference `JsonApiClient` sends `x-request-id` and `recouv.application.id`, only builds GET and POST-with-body
requests, and refuses non-JSON bodies. The CESU contract declares no request header (§1, §5.15), uses `DELETE /mandats`
and `POST` with query parameters only (030, 060), and answers 030/031 with an empty 200 body. 512 is an authentication
error (§1).

## Decision

- `Http\ApiRequest` supports `get(op, path, query)`, `post(op, path, query, ?body)` and `delete(op, path, query)`;
  query strings are encoded with RFC 3986.
- `JsonApiClient` treats an empty 2xx body as "no payload" (`ApiResponse::isEmpty()`); mappers for 030/031 require it
  empty or `{}`, every other operation requires a JSON object.
- No extra header by default. `ApiConfiguration` exposes optional `requestIdHeader` and `applicationIdHeader`
  (name + value) for when URSSAF asks for them; `User-Agent` is always sent.
- Status mapping: 2xx decode; 400 `UrssafRequestRejected` (errors parsed, see ADR 0005); 401 → invalidate the token and
  replay once, then `UrssafAuthenticationFailed`; 403 and 512 `UrssafAuthenticationFailed`; 429, 500, 502, 503, 504
  `UrssafUnavailable` (with `Retry-After` seconds when present); anything else `UnexpectedUrssafResponse`. 500 bodies
  are parsed (`ERR_TECHNIQUE` / `ERREUR_TECHNIQUE`) and kept on the exception.
- Rate limiting: see ADR 0011.

## Consequences

- The client stays a port-free internal class, as in the reference; only its request shapes and header policy differ.
- When the contract tests settle the headers, enabling them is configuration only.
