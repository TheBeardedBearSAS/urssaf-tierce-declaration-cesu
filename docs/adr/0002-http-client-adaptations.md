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
- No extra header by default. `ApiConfiguration` exposes an optional request-id header (name only: the value is a
  UUID generated per attempt by the `IdGenerator` port, so that each call can be correlated) and an optional
  application-id header (name + fixed value) for when URSSAF asks for them; `User-Agent` is always sent, and
  `Accept: application/json` (content negotiation, not a contract header) too. `Content-Type: application/json` is
  sent only when the request has a body (never for 030, 060, GET or DELETE). (lead-accepted 2026-11-15)
- Status mapping: 2xx decode; 400 `UrssafRequestRejected` (errors parsed, see ADR 0005); 401 → invalidate the token and
  replay once, then `UrssafAuthenticationFailed`; 403 and 512 `UrssafAuthenticationFailed`; 429, 500, 502, 503, 504
  `UrssafUnavailable` (with `Retry-After` seconds when present); anything else `UnexpectedUrssafResponse`, except a
  5xx on 010, 050, 060 and 070, see ADR 0003. The error bodies of 429 and of every listed 5xx are parsed
  (`ERR_TECHNIQUE` / `ERREUR_TECHNIQUE`, lenient: no errors on an unreadable body) and kept on the exception. (lead-accepted 2026-11-15)
- On the token endpoint, 400, 401 and 403 are `UrssafAuthenticationFailed` but every 5xx, 512 included, is a
  `UrssafUnavailable` (token call, `whileAuthenticating`); on an API call, 512 is `UrssafAuthenticationFailed`, like 403. (lead-accepted 2026-11-15)
- The replay after a 401 applies to every operation, 010, 050, 060 and 070 included: a 401 proves the call was not
  processed. (lead-accepted 2026-11-15)
- The cause of a transport failure is sanitised: the exceptions carry an internal `TransportError` holding only the
  class name of the PSR-18 exception, never its message, which usually embeds the full URI and therefore the query of
  020 / 021 (national identification number, names, birth dates; ADR 0007). (lead-accepted 2026-11-15)
- Rate limiting: see ADR 0011.

## Consequences

- The client stays a port-free internal class, as in the reference; only its request shapes and header policy differ.
- When the contract tests settle the headers, enabling them is configuration only.
