# `/api/v1` sign-in and businesses

**New in SaaS stage 3. The shipped app doesn't use these yet; the flat `/api` endpoints are unchanged.**

A login can belong to several businesses. Every `/api/v1` token acts on **one** business; switching issues
a new token. Flat `/api` tokens keep acting on the user's default business.

All responses are `{ "success": bool, "message": string|null, "data": … }`. Errors follow the flat API:
422 for validation (`errors.<field>[0]`), 403 for refusals, 404 for a business the user can't work in.
401 only means the token is missing, revoked or unknown.

## `POST /api/v1/auth/sign-in`

Throttled like `/login` (5 per minute per IP and per email).

| Field | Required | Notes |
|---|---|---|
| `email`, `password` | yes | |
| `device_name` | yes | the token's name, up to 255 characters |
| `business` | no | a business uuid; needed only when the response asked for one |

Which business the token is for:
1. `business` when given (it must be one the user can work in, otherwise 422 on `business`); it becomes
   the default if the user had no usable default.
2. Otherwise the user's default business, if usable.
3. Otherwise their only usable business.
4. Otherwise no token: `data.business_required` is `true`, `data.token` is `null`, and `data.businesses`
   lists the choices; sign in again with `business`.

A suspended business or an inactive membership is never chosen. A user with no usable business gets 403;
wrong credentials or an inactive account get 422 on `email`.

```jsonc
{
  "success": true,
  "message": "Signed in.",
  "data": {
    "business_required": false,
    "token": "12|…",
    "business": { "uuid": "…", "name": "Alpha Stores", "currency": "KES", "timezone": "Africa/Nairobi", "is_default": true },
    "businesses": [ { "uuid": "…", "name": "Alpha Stores", "currency": "KES", "timezone": "Africa/Nairobi", "is_default": true, "is_current": true } ],
    "user": { "uuid": "…", "name": "…", "email": "…", "phone": "…", "roles": ["admin"], "permissions": ["sales.view", "…"] }
  }
}
```

`roles` and `permissions` are the ones held **in that business**; they change when switching.

## With a `/api/v1` token

A flat `/api` token is refused here with 403 (sign in to `/api/v1` for a token).

- `GET /api/v1/businesses`: the businesses the user can work in, each with `is_current` and
  `is_default`.
- `POST /api/v1/businesses/{business uuid}/switch`: returns the same `data` as sign-in for the other
  business, with a new token; **the token used is revoked**. 404 if the user can't work in it.
- `POST /api/v1/auth/sign-out`: revokes the token.

A token stops reaching any data as soon as its membership is removed or suspended, or the business is
suspended; it is never moved to another business.
