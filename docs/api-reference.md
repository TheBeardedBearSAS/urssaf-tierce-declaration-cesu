# URSSAF "API Tierce Déclaration CESU" — technical reference

This document is the single source of truth used by the package for enums, validation rules and wire fixtures.
It summarises the official specification:

- Swagger contract: **v2.0.0** (`docs/openapi/td-cesu-prd.json` and `docs/openapi/td-cesu-sb.json`; the two files are
  identical except for `host`, see §1)
- Technical documentation PDF: **v2.0.2** (2026-07-28), file name `Documentation-API-CESUV1_v2.0.2.pdf`
  (`docs/reference/Documentation-API-CESUV1_v2.0.2.pdf`, 84 pages)
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/td-cesu (restricted access, subscription through DataPass)
- PDF: https://cedito-medias.urssaf.fr/medias/PortailAPI/files/documentationAPIs/CESU/Documentation-API-CESUV1_v2.0.2.pdf

The package constant `SpecVersion::CURRENT` must equal `2.0.2`. Rule shared by the URSSAF packages: `SpecVersion` is
the version in the **file name of the published PDF** (the most detailed source), falling back to the Swagger version
when the PDF is not versioned. Here the PDF is versioned; since PDF v2.0.1 (2025-11-07) its numbering is meant to
match the API version (the portal showed 2.0.0 at that date), which is why Swagger (2.0.0) and PDF (2.0.2) differ.
Field names, codes and wire values below are reproduced verbatim from the specification (they are French by design,
some without accents in the Swagger); everything else in the package is English.

> Status: implemented progressively, see [roadmap.md](roadmap.md).

Local lifecycles, idempotency and retries: [workflow.md](workflow.md). Public integration events: [integration-events.md](integration-events.md).

Wording used below: *PE* = particulier employeur (private employer), *SP* = salarié particulier (employee), *AF* =
accueillant familial (family foster carer), *AICI* = avance immédiate du crédit d'impôt (immediate tax credit advance),
*PAS* = prélèvement à la source (withholding at source, art. 204A CGI), *NIR* = social security number.

> **Source and rights.** The specification, the field names, the codes and the official JSON examples quoted here come
> from the URSSAF documents listed above and remain URSSAF's property. They are reproduced only so the package can
> interoperate with the API, and they are not covered by this package's MIT license. In case of discrepancy, the
> official URSSAF documentation prevails. The official documents themselves are not versioned: download them into
> `docs/reference/`, a local git-ignored folder. This package is not affiliated with URSSAF.

---

## 1. General

| Topic | Value |
|---|---|
| Purpose | Lets a digital platform act as **third-party declarant** for a private employer: enrol the employer in the simplified Cesu offer, activate Cesu+ for an employer/employee pair, activate the immediate tax credit advance (AICI), estimate contributions, declare the salary paid to an employee (including withholding at source), and — with Cesu+ — declare and pay the employee through the CNCESU. When AICI is active and for Cesu+ declarations only, the advance is deducted from the amount the employer must pay. |
| Access | Catalogue: restricted, subscription through DataPass. PDF §2.1: restricted offer, access request validated by ACOSS. Credentials (client id / client secret) issued after approval. |
| Security | OAuth2 **client credentials** (Swagger `securityDefinitions`: type `oauth2`, flow `application`) |
| Token endpoint | `POST https://api.urssaf.fr/api/oauth/v1/token` (form-urlencoded: `grant_type=client_credentials`, `client_id`, `client_secret`, `scope`). The contract (prd **and** sb) names this production URL; the sandbox token host is not published (see §5). |
| Scope | `tiersdecl.cesu` ("Acces a l'API Tierce Declaration CESU") |
| Production base URL | `https://api.urssaf.fr/tiersdecl/v1/cesu` (Swagger `host` `api.urssaf.fr` + `basePath` `/tiersdecl/v1/cesu`) |
| Sandbox base URL | `https://api-edi.urssaf.fr/tiersdecl/v1/cesu` (Swagger `td-cesu-sb.json` `host`; "environnement Éditeur") |
| Content type | `application/json` (request and response). `POST /mandats` and `POST …/cesuplus` carry no body (query parameters only). |
| Request headers | None declared by the contract (no `x-request-id` or similar). |
| HTTP errors | 200, 400 (validation / business), 401, 403, 429 (quota, PDF only), 500, 512 (authentication, declared for every path except `/mandats` in the Swagger; the PDF lists 401/403/512 for every method) |

Package side (already in place, do not change):

- `Infrastructure\Urssaf\Environment`: enum `Sandbox` (`https://api-edi.urssaf.fr`) and `Production`
  (`https://api.urssaf.fr`); `baseUri()` returns the host only. The sandbox host must stay overridable by configuration.
- `Infrastructure\Urssaf\UrssafEndpoints`: `TOKEN_PATH = /api/oauth/v1/token`, `TOKEN_SCOPE = tiersdecl.cesu`,
  `API_BASE_PATH = /tiersdecl/v1/cesu`. Operation URL = `Environment::baseUri()` + `API_BASE_PATH` + path of §2; token
  URL = `Environment::baseUri()` + `TOKEN_PATH` (this assumes the sandbox token endpoint lives on the sandbox host: *inferred*
  from the other URSSAF packages, see §5).
- `SpecVersion::CURRENT = '2.0.2'`.

Swagger tags: `Au sujet des PE` (employer: 010, 020, 040, 050, 060, 070), `Au sujet des SP` (employee: 021),
`Mandat TD` (mandates: 030, 031).

### 1.1 Quotas (HTTP 429 when exceeded)

PDF §2.5: default quota of **2 500 requests per hour in production**, at two levels: a global maximum per API
(availability) and a quota per API client (fairness). Sandbox values and per-operation limits are **not published**.
The `429` response is not declared in the Swagger and its body / `Retry-After` header are not published.

### 1.2 Known limitations (official)

- PDF §1.2 *Multi-plateforme*: an employer **cannot mandate several platforms at the same time** (one third-party
  declarant per employer account). A second platform gets `COMPTE_DEJA_MANDATE` on 030.
- Cesu+ requires AICI: "Il n'est pas possible de réaliser une déclaration Cesu+ sans avoir activé l'AICI" (PDF §3.7.1);
  a Cesu declaration at 0 is impossible.
- Cesu+ declarations: must be received **no later than the 15th of the month following the declared month**, and the net
  salary for a period may not exceed **6 000 €**; a standard ("socle") Cesu declaration stays possible after the deadline.
- Declarations for family foster carers (`AF`) are rejected through the API (error text "Il n'est pas possible de réaliser
  de déclaration pour les accueillants familiaux via l'API"), although the contract still carries `revenusAf` and
  `agrementAF` (see §2.7 and §5).
- An employer whose SEPA direct debit mandate is not active cannot declare, on the website or through the API; this can
  only be fixed by the employer on cesu.urssaf.fr (PDF §3.2.3).
- The employer's online account does not need to be activated to use the API when the SEPA mandate was provided at
  registration (PDF §3.1.1).

### 1.3 Error payload

Business and validation errors (HTTP 400) and technical errors (HTTP 500) are a **single JSON object** `Erreur`
(`code`, `message`, `description`, all strings; `description` may be `null` in the PDF examples). No example shows a
list; the AVCS-style list form is not documented (see §5).

```json
{ "code": "PARAM_INVALIDE", "message": "Un des paramètres de votre demande est invalide", "description": "Le paramètre dateNaissance est invalide" }
```

Technical error (500): `{"code":"ERR_TECHNIQUE","message":"Erreur technique","description":"Une erreur système est survenue"}`
for 010, 020, 021, 040, 050, 060, 070; **`ERREUR_TECHNIQUE`** with message "Une erreur technique est survenue" for 030 and 031.
401, 403 and 512 have no documented body.

`ERR_METIER` is a generic business code: the **`description` text is the only discriminator** (no stable sub-code). Its
`message` is `" Une erreur métier est survenue"` (leading space, sic) for most cases and `"Une erreur métier cesu+ est survenue"`
for the Cesu+ checks of 050. The catalogue is in §3.13.

### 1.4 Third-party declaration mandate (*mandat*) and prerequisites

The platform must hold a **mandate** from the employer to act as third-party declarant, and notifies URSSAF of it with
**030**; **031** notifies its cancellation. The mandate is identified by the employer's `numeroCesu` and `dateNaissance`
(no mandate identifier is returned or accepted). Effects (PDF §3.4.1 / §3.5.1): URSSAF stores the information so that
the CESU information system accepts (030) or refuses (031) API declarations from this platform for this employer, and
sends a letter or e-mail to the employer about the mandate (or its cancellation).

| Operation | Mandate prerequisite | Other prerequisites |
|---|---|---|
| 010 `POST /employeurs` | none published (creates the account; whether it implies a mandate is not published, the Swagger says it also "habilite" the employer to the third-party declaration services) | SEPA mandate flag `rib.mandatSepa` |
| 020 `GET /employeurs/{numeroCesu}` | none published; returns `mandatPresent` | — |
| 021 `GET /salaries` | none published | — |
| 030 `POST /mandats` | creates it | account must exist (`ER_API_020_0000`); no other platform mandated (`COMPTE_DEJA_MANDATE`) |
| 031 `DELETE /mandats` | removes it | account must exist; the mandate must belong to this platform (`COMPTE_DEJA_MANDATE` documented for "mandat présent pour un autre établissement") |
| 040 `POST /estimer` | none published (simulation, no employer identified) | — |
| 050 `POST …/declarer` | **required** (`MANDAT_INEXISTANT`) | active SEPA mandate, employer RIB (`RIB_ABSENT`); for Cesu+: Cesu+ and AICI active (§2.8, §2.9) |
| 060 `POST …/cesuplus` | **required** (`MANDAT_INEXISTANT`) | existing employer/employee contract, no Cesu+ blocking reason |
| 070 `POST …/aici` | none published | Cesu+ active on the employer account; eligibility (no APA/PCH exemption) |

Typical order (*inferred from the error catalogue*): 010 (if the employer is new) → 030 → 060 → 070 → 050 with
`typeDeclaration = 1`; 050 with `typeDeclaration = 0` only needs the mandate.

---

## 2. Operations

Conventions: `Req.` is `yes` / `no` / `cond.` (conditional, see Rules). Types follow the Swagger. "PDF only" / "Swagger only"
marks a discrepancy (all collected in §5). All dates are **date-only** `YYYY-MM-DD` (`format: date`), never date-time.
Fixture file names are relative to `tests/Fixtures/wire/`; fixtures marked *assembled* were built from the per-field
`example` values of the Swagger (the PDF has no example for that payload) and are **not** official end-to-end examples.

### 2.1 CESU010 — Register an employer — `POST /employeurs`

`operationId`: `inscriptionPeCesu`. Tag `Au sujet des PE`. Registers a private employer with the Cesu scheme and entitles
them to the third-party declaration services. Before creating the employer, URSSAF checks **uniqueness of the e-mail**. On
success URSSAF stores the data and sends a letter or e-mail asking the employer to validate the online Cesu account. The
account does not have to be activated to use the API if the SEPA mandate was given (`rib.mandatSepa`).

Body: `InputInscriptionPe` (all of `adresseEmploi`, `codeCivilite`, `dateNaissance`, `email`, `nom`, `prenom`, `rib`,
`telephone` are required by the Swagger).

| Field | Type | Req. | Rules |
|---|---|---|---|
| `codeCivilite` | string | yes | `"1"` (M.) or `"2"` (Mme), see §3.1 |
| `adresseEmploi` | `Adresse` | yes | employer address, see below |
| `nom` | string | yes | max 30; pattern `[a-zA-ZáÁàÀâÂäÄéÉèÈëËêÊìÌíÍïÏîÎñÑòÒóÓôÔöÖùÙúÚüÜûÛçÇýÝÿŸŒœÆæ'\-\s]*` (PDF; the Swagger JSON carries an ASCII-folded, degenerate version of it) |
| `prenom` | string | yes | same rules as `nom`, max 30 |
| `dateNaissance` | string (date) | yes | `YYYY-MM-DD`, example `2019-01-30`; employer must be **at least 16 and at most 110 years old** (business errors) |
| `email` | string | yes | max 256; pattern `^[-A-Za-z0-9_]+(\.[-A-Za-z0-9_]+)*@[A-Za-z0-9]+((-\|\.)[A-Za-z0-9]+)*\.[A-Za-z]+$`; must be unique in the Cesu scheme |
| `telephone` | string | yes | max 10 (no minimum on this object, unlike `InfosSalarie.telephone`); pattern `^$\|.*[0-9]{10}.*`, 10 digits |
| `rib` | `Rib` | yes | bank details, see below |

`Adresse` (shared with `infosSalarie.adresse`, §2.7). No field is `required` in the Swagger; PDF §3.1.4 states the rules:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `numVoie` | integer (int32) | cond. | 1–9999 (Swagger/PDF); required if `lettreVoie` is set |
| `lettreVoie` | string | no | exactly 1 char, code from §3.9 (`PARAM_INVALIDE` otherwise) |
| `typeVoie` | string | no | 1–3 chars, code from §3.10, e.g. `R` for RUE (`PARAM_INVALIDE` otherwise) |
| `libelleVoie` | string | cond. | max 21; required if `numVoie` or `typeVoie` is set |
| `complementLocalisation` | string | no | max 32, e.g. `Batiment A` |
| `lieuDit` | string | no | max 32, e.g. `La Rotonde` |
| `codeCommune` | string | cond. | INSEE municipality code, exactly 5 chars, e.g. `94022`; required when the postal code does not identify a single municipality |
| `codePostal` | string | yes (PDF) | exactly 5 chars. "Le code postal est obligatoire" in the PDF; not `required` in the Swagger |

Address rules (PDF §3.1.4): at least one of `libelleVoie`, `numVoie`, `typeVoie`, `lettreVoie`, `complementLocalisation`,
`lieuDit` must be set (business error "Veuillez indiquer votre adresse complète." when none of road, complement, locality
is given). Each address line must stay within **32 characters**: `numVoie` + `lettreVoie` + space + `typeVoie` + space +
`libelleVoie`. If the 21-char `libelleVoie` limit is too short, terms may be abbreviated (example `10 R DU FG SAINT DENIS`
→ `typeVoie: "R"`, `libelleVoie: "DU FG SAINT DENIS"`, `codeCommune: "75110"`, `codePostal: "75010"`); as a last resort
(not recommended) the whole line goes in `complementLocalisation`.

`Rib` (all three required):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `iban` | string | yes | 1–34 chars; server checks (see business errors): at least 14 chars, 2-letter country code other than `QX`, 2 numeric check digits that are correct, non-empty account part of digits / upper-case letters, **SEPA-area prefix only** |
| `bic` | string | yes | 1–11 chars; server checks: 8 or 11 chars, 4 alpha bank code, 2 alpha country code, 7th char alpha or digit 2–9, 8th char alpha except `O` or digit 0–9, 11-char form: alphanumeric branch code, SEPA country, and for `FR` accounts the bank counter must be known and match the BIC |
| `mandatSepa` | boolean | yes | Swagger: "Accord du PE donné à la plateforme pour télédéclarer"; PDF: "Indicateur mandat SEPA donné à la plateforme". `SEPA_ABSENT` when the authorisation is missing (*inferred*: when `false`) |

Response 200: `OutputInscriptionPe`

| Field | Type | Rules |
|---|---|---|
| `numeroCesu` | string | the employer's Cesu number; alphanumeric, 14 characters (*inferred from the path parameter description and the example `Z0003752390009`*) |

Business and validation errors (HTTP 400, PDF §3.1.3):

| `code` | Condition | `description` (verbatim) |
|---|---|---|
| `PARAM_INVALIDE` | a parameter is missing or malformed | "Le paramètre dateNaissance est invalide" (example; message "Un des paramètres de votre demande est invalide") |
| `SEPA_ABSENT` | no SEPA mandate authorisation | `null` (message "Absence d''autorisation de mandat SEPA", sic) |
| `ERR_METIER` | invalid IBAN (rules above, non-SEPA prefix excluded) | "Veuillez indiquer un IBAN valide." |
| `ERR_METIER` | IBAN prefix outside SEPA | "Veuillez indiquer un IBAN valide – préfixe de l’IBAN non SEPA." |
| `ERR_METIER` | invalid BIC, or BIC country outside SEPA | "Veuillez indiquer un BIC valide." |
| `ERR_METIER` | bank details on the Cesu blacklist | "CoordBancaires blacklist." |
| `PARAM_INVALIDE` | unknown `lettreVoie` | "Erreur sur le champ lettreVoie : le code de la lettre de voie est incorrect." (message "La requête est incorrecte") |
| `PARAM_INVALIDE` | unknown `typeVoie` | "Erreur sur le champ typeVoie : le code du type de voie est incorrect." |
| `ERR_METIER` | civility missing / name missing / name invalid | "Veuillez indiquer votre civilité." / "Veuillez indiquer votre nom." / "Veuillez indiquer un nom valide." |
| `ERR_METIER` | first name missing / invalid | "Veuillez indiquer votre prénom." / "Veuillez indiquer un prénom valide." |
| `ERR_METIER` | forbidden acronyms in name or first name | "Vous ne pouvez pas créer de compte Cesu en tant que : SCP, SNC, SCM, SA, SARL, EURL, SCI, SELARL, UDAF, église, paroisse, association." |
| `ERR_METIER` | birth date missing | "Veuillez indiquer votre date de naissance." |
| `ERR_METIER` | employer under 16 | "Vous devez avoir au moins 16 ans pour activer ce service." |
| `ERR_METIER` | employer over 110 | "Veuillez indiquer une date de naissance valide." |
| `ERR_METIER` | none of road / complement / locality | "Veuillez indiquer votre adresse complète." |
| `ERR_METIER` | `lettreVoie` without `numVoie` | "Veuillez indiquer votre numéro de voie." |
| `ERR_METIER` | `numVoie` and/or `typeVoie` without `libelleVoie` | "Veuillez indiquer votre nom de voie." |
| `ERR_METIER` | invalid municipality | "Veuillez indiquer un code postal et une commune valides." |
| `ERR_METIER` | invalid phone | "Veuillez indiquer un numéro de téléphone valide." |
| `ERR_METIER` | invalid e-mail | "Veuillez indiquer un identifiant valide, avec au moins 1 caractère, le séparateur @, l'hébergeur et l'extension." |
| `ERR_METIER` | e-mail already known | "Cet identifiant est déjà connu. Si vous disposez d'un compte Cesu vous pouvez réinitialiser votre mot de passe à partir de la rubrique "Problème d'identification"." |
| `ERR_TECHNIQUE` | HTTP 500 | "Une erreur système est survenue" |

PDF history note: the `ERR_METIER` meaning for this call was added in PDF 1.4.1 (2021-06-01).

Fixtures: `register_employer_request.json` (PDF curl body, verbatim), `register_employer_response.json`,
`error_sepa_absent.json`, `error_err_metier_iban.json`, `error_param_invalide.json`, `error_technical.json`.

### 2.2 CESU020 — Verify an employer account — `GET /employeurs/{numeroCesu}`

`operationId`: `verificationPeCesu`. Tag `Au sujet des PE`. Checks that the person identified by Cesu number and birth date
holds a Cesu PE account, and reports the state of the account, the SEPA mandate, the **third-party mandate for this
platform**, Cesu+ and AICI. Read-only: no data stored, no letter or e-mail sent.

Path and query parameters:

| Field | In | Type | Req. | Rules |
|---|---|---|---|---|
| `numeroCesu` | path | string | yes | employer Cesu number, alphanumeric, exactly 14 chars |
| `dateNaissance` | query | string (date) | yes | employer birth date `YYYY-MM-DD`; pattern `([0-9]{4})-(?:[0-9]{2})-([0-9]{2})`; must match the account (`ERR_METIER` otherwise) |
| `numeroCesuSala` | query | string | cond. | employee Cesu number, exactly 14 chars; required if no `nirSala` and no administrative data |
| `nirSala` | query | string | cond. | employee NIR, 15 chars (no pattern published); required if no `numeroCesuSala` and no administrative data |
| `prenomSala` | query | string | cond. | max 30, alphabetic; required without `numeroCesuSala` and `nirSala` |
| `nomNaissanceSala` | query | string | cond. | max 30, alphabetic; same rule |
| `nomUsageSala` | query | string | no | max 30, alphabetic; used only without Cesu number and NIR |
| `dateNaissanceSala` | query | string (date) | cond. | `YYYY-MM-DD`; same rule; **not checked** when a Cesu number or NIR is given (PDF) |
| `departementNaissanceSala` | query | string | cond. | max 3 chars (e.g. `972`, `07`); required if the employee was born in France (`codePaysNaissanceSala` = `100`) |
| `villeNaissanceSala` | query | string | cond. | max 30, alphabetic; same rule as `prenomSala` |
| `codePaysNaissanceSala` | query | string | cond. | INSEE country code (France = `100`); same rule as `prenomSala` |

The employee data identifies the pair whose Cesu+ state is returned. PDF example (employer only):
`GET https://api.urssaf.fr/tiersdecl/v1/cesu/employeurs/Z0003752390009?dateNaissance=1975-01-30`. Whether the employee
parameters are mandatory for an employer-only check is ambiguous (all optional in the Swagger, example without them, but
the PDF documents `PARAM_INVALIDE` when no employee identification is present): see §5.

Response 200: `OutputVerificationPe`

| Field | Type | Rules |
|---|---|---|
| `inscrit` | boolean | the PE is registered with Cesu |
| `etatCompteEnLigne` | string | online account state: `absent`, `inactif`, `actif`, `bloque`, `radie` (§3.4) |
| `etatMandatSepa` | string | SEPA direct debit mandate state: `absent`, `inactif`, `actif` (§3.4) |
| `mandatPresent` | boolean | a third-party declaration mandate exists **for this account and this platform** |
| `etatCesuPlus` | string | Cesu+ state for the employer/employee pair. Swagger: `active`, `bloque`, `desactive`; PDF: `activé`, `bloqué`, `désactivé` (§3.4, §5) |
| `motifBlocageCesuPlus` | string | existing Cesu+ blocking reason for the employer account (e.g. `C00`); PDF spells the field `motifblocageCesuPlus`; the PDF example carries the string `"null"` |
| `etatAICI` | boolean | AICI state for the employer |

Errors (HTTP 400, PDF §3.2.3):

| `code` | Condition | `description` (verbatim) |
|---|---|---|
| `PARAM_INVALIDE` | missing / malformed parameter | "Le paramètre dateNaissance est invalide" (example) |
| `ERR_METIER` | birth date differs from the account's | " La date de naissance ne correspond pas à celle de l''employeur identifié par le numéro CESU renseigné." |
| `PARAM_INVALIDE` | no employee Cesu number, NIR or identification data | " Le numéro de CESU, le NIR ou les données identifiants le salarié doivent être renseignées (prénom, nom de naissance, date de naissance, pays de naissance, ville de naissance et le département de naissance si le salarié est né en France)." (message " La requête est incorrecte") |
| `ERR_METIER` | no link between employer and employee (no service ever performed) | " Il n'existe aucune lien entre le particulier employeur et le salarié (aucune prestation n'a été réalisée par ce salarié pour l'employeur)…" (message `Aucune lien ne lie le particulier employeur et le salarié saisies`, quotes broken in the PDF) |
| `ERR_TECHNIQUE` | HTTP 500 | "Une erreur système est survenue" |

Discrepancies: enum values and `motifBlocageCesuPlus` casing as above; the PDF example uses `"activé"`, a lower-case
`motifblocageCesuPlus` set to the string `"null"`, spaces inside two key names and a trailing comma (invalid JSON).

Fixtures: `verify_employer_response.json` (PDF example **repaired**: key spaces trimmed, trailing comma removed, PDF
spelling `motifblocageCesuPlus` and `"activé"` kept), `verify_employer_response_assembled.json` (Swagger spelling and
enum values, built from the field examples), `error_param_invalide.json`.

### 2.3 CESU021 — Verify an employee account — `GET /salaries`

`operationId`: `verificationSpCesu`. Tag `Au sujet des SP`. Searches the Cesu employee repository for the person
described by the input. Read-only (no data stored, no letter or e-mail sent).

Query parameters (all optional in the Swagger; same semantics as the `…Sala` parameters of §2.2, without the suffix):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `numeroCesu` | string | cond. | exactly 14 chars; required without `nir` and administrative data |
| `nir` | string | cond. | 15 chars; required without `numeroCesu` and administrative data |
| `prenom` | string | cond. | max 30, alphabetic; required without `numeroCesu` and `nir` |
| `nomNaissance` | string | cond. | max 30, alphabetic; same rule |
| `nomUsage` | string | no | max 30, alphabetic; optional, used only without `numeroCesu` and `nir` |
| `dateNaissance` | string (date) | cond. | `YYYY-MM-DD`, pattern `([0-9]{4})-(?:[0-9]{2})-([0-9]{2})`; same rule; not checked when `numeroCesu` or `nir` is given |
| `departementNaissance` | string | cond. | max 3 chars (e.g. `972`, `07`); required if born in France (`codePaysNaissance` = `100`) |
| `villeNaissance` | string | cond. | max 30, alphabetic; same rule as `prenom` |
| `codePaysNaissance` | string | cond. | INSEE country code (France = `100`); same rule as `prenom` |

PDF example: `GET …/salaries?nir=269054958815780` (shown with host `editor.swagger.io`).

Response 200: `OutputVerificationSp`

| Field | Type | Rules |
|---|---|---|
| `inscrit` | boolean | the SP is registered with Cesu |
| `numeroCesu` | string | the employee's Cesu number (14 chars, *inferred from the example*). Presence when `inscrit` is `false` is not published |

Errors (HTTP 400): `PARAM_INVALIDE` (generic, same example as §2.2) and `PARAM_INVALIDE` with the "Le numéro de CESU, le
NIR ou les données identifiants le salarié doivent être renseignées…" description when none of Cesu number, NIR or
identification data is present; `ERR_TECHNIQUE` (500). The outcome for an unknown person (HTTP 200 with `inscrit: false`
versus an error) is not published (see §5).

Discrepancy: PDF example `"numeroCesu": " Z0003752390009"` has a leading space (typo).
Fixtures: `verify_employee_response.json` (PDF example, leading space removed), `error_param_invalide.json`.

### 2.4 CESU030 — Register a mandate — `POST /mandats`

`operationId`: `enregistrerMandatCesu`. Tag `Mandat TD`. Notifies URSSAF that the platform obtained a third-party
declarant mandate from this employer (see §1.4). No request body.

Query parameters:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `numeroCesu` | string | yes | employer Cesu number, alphanumeric, exactly 14 chars |
| `dateNaissance` | string (date) | yes | employer birth date `YYYY-MM-DD`; pattern `([0-9]{4})-(?:[0-9]{2})-([0-9]{2})` |

PDF example: `POST https://api.urssaf.fr/tiersdecl/v1/cesu/mandats?numeroCesu=Z0003752390009&dateNaissance=1975-10-01`.

Response 200 "Mandat ajoute": **empty body** (PDF: "http code 200").

Errors (HTTP 400 unless stated):

| `code` | Condition | `message` / `description` |
|---|---|---|
| `PARAM_INVALIDE` | malformed parameter | "Un des paramètres de votre demande est invalide" / "La valeur 1990-11-256 est incorrecte" |
| `COMPTE_DEJA_MANDATE` | mandate already present for another establishment | "Le compte est déjà mandaté" / "Il existe déjà un autre établissement mandataire pour ce numéro de compte" |
| `ER_API_020_0000` | employer unknown | "Aucun compte CESU trouvé avec ces paramètres" (both fields) |
| `ERREUR_TECHNIQUE` | HTTP 500 | "Une erreur technique est survenue" / "Impossible de joindre le service cesu afin de vérifier l'existence du compte fourni" |

Notes: the Swagger declares 200, 400, 401, 403, 500 and **no 512** for this operation (the PDF lists 401/403/512). PDF
2.0.2 updated the error messages of 030 and 031. Re-registering a mandate this platform already holds is not documented.

Fixtures: `error_compte_deja_mandate.json`, `error_account_unknown.json`, `error_technical_mandate.json`,
`error_param_invalide.json` (shape).

### 2.5 CESU031 — Cancel a mandate — `DELETE /mandats`

`operationId`: `annulerMandatCesu`. Tag `Mandat TD`. Notifies URSSAF that the mandate was cancelled; URSSAF will refuse API
declarations from this platform for this employer and informs the employer by letter or e-mail. No request body.

Query parameters: same as §2.4 (`numeroCesu`, `dateNaissance`, both required).
PDF example: `DELETE https://api.urssaf.fr/tiersdecl/v1/cesu/mandats?numeroCesu=Z0003752390009&dateNaissance=1975-10-01`.

Response 200 "Mandat annule": **empty body**.

Errors: same four as §2.4, with `COMPTE_DEJA_MANDATE` documented as "Mandat présent pour le PE mais pour un autre
établissement" (cancelling a mandate held by another platform). Same Swagger gap (no 512). Whether cancelling when no
mandate exists is an error is not published.

Fixtures: same as §2.4.

### 2.6 CESU040 — Estimate contributions — `POST /estimer`

`operationId`: `estimerCotisationsCesu`. Tag `Au sujet des PE`. Simulates social contributions for a Cesu activity.
Read-only (nothing stored). No employer is identified.

Body: `InputEstimation`

| Field | Type | Req. | Rules |
|---|---|---|---|
| `typeEmploi` | string | yes | `PE` (emploi familial) or `AF` (accueillant familial), §3.2 |
| `revenusEf` | `RevenusEfSimu` | no | employee-type income (`PE`), see below |
| `revenusAf` | `RevenusAfSimu` | no | foster-carer income (`AF`), see below |

Which of `revenusEf` / `revenusAf` goes with which `typeEmploi` is *inferred from the business error texts* (the contract
does not state it).

`RevenusEfSimu` = `RevenusEfBase` + simulation fields (no `periodesCongesPayes` here, unlike §2.7):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `salaireHoraire` | number (double) | yes | max 999.99, multiple of 0.01 |
| `heuresTravaillees` | integer (int32) | yes | 0–200 |
| `nbHeureSupplementaire1` | integer | no | 0–35; overtime paid +25 % (from 40 normal hours) |
| `nbHeureSupplementaire2` | integer | no | 0–40; overtime paid +50 % (from 40 normal hours and 8 hours at +25 %) |
| `heuresCp` | integer | no | 0–200; paid-leave hours; defaults to 0 when omitted (PDF) |
| `complementsSalaire` | array of `ComplementSalaire` | no | see §2.7 |
| `typeSalaire` | string | yes | `BRUT` or `NET`, §3.5 |
| `codeExoneration` | string | yes | exactly 2 chars, code from §3.6 (e.g. `02`); only used for simulation, ignored on declaration |
| `codeZoneGeographique` | string | yes | exactly 5 chars, §3.7 |
| `congesPayes` | boolean | yes | `true` = the 10 % paid-leave share is included in the hourly wage; `false` = not included and `heuresCp` applies (see §5 for a contradictory PDF paragraph) |

`RevenusAfSimu` = `RevenusAf` (§2.7) + required `codeZoneGeographique` (exactly 5 chars, §3.7).

Response 200: `OutputEstimation`

| Field | Type | Rules |
|---|---|---|
| `cotisations` | `Cotisations` | `cotisationsPatronales` (number, total employer contributions), `cotisationsSalariales` (number, total employee contributions) |

Errors (HTTP 400, PDF §3.6.3):

| `code` | Condition | `description` (verbatim) |
|---|---|---|
| `PARAM_INVALIDE` | generic | "Le paramètre typeEmploi est invalide" (example) |
| `PARAM_INVALIDE` | bad `codeExoneration` | "Erreur sur le champ codeExoneration : le type d'exonération renseigné est incorrect." |
| `PARAM_INVALIDE` | bad `typeEmploi` | "Erreur sur le champ typeEmploi : le type d'emploi renseigné est incorrect." |
| `PARAM_INVALIDE` | bad `typeSalaire` | "Erreur sur le champ typeSalaire : le type de salaire renseigné est incorrect." |
| `PARAM_INVALIDE` | bad `codeZoneGeographique` | "Erreur sur le champ codeZoneGeographique : la zone géographique renseignée est incorrecte." |
| `ERR_METIER` | PE: wage below SMIC | " Le salaire horaire ne peut être inférieur à XX,XX €." |
| `ERR_METIER` | PE: overtime with fewer than 40 normal hours | " La déclaration d'heures majorées est impossible si le nombre d'heures normales est inférieur à 40." |
| `ERR_METIER` | PE: 50 % overtime with fewer than 8 hours at 25 % | "Pour déclarer des heures majorées à 50%, le nombre d'heures majorées à 25% doit être supérieur ou égal à 8." |
| `ERR_METIER` | PE: specific-hours wage below SMIC × coefficient | " Le salaire horaire net pour une heure spécifique XXX ne peut être inférieur à XX,XX€." |
| `ERR_METIER` | PE: employee participation without benefits in kind / without hours and wage / benefits' contributions above net pay | "La déclaration d'une participation financière du salarié est impossible si aucun avantage en nature n'est renseigné." / "… si le nombre d'heures et le salaire horaire net ne sont pas renseignés." / "Le montant des cotisations salariales calculé sur Avantage en nature est supérieur au salaire net déclaré." |
| `ERR_METIER` | AF: no income element | " Vous devez saisir au moins un élément de rémunération." |
| `ERR_METIER` | AF: total reception days above 31.5 | " Le nombre total de journées d''accueil ne peut être supérieur à 32." |
| `ERR_METIER` | AF: SMIC/day (full-time or part-time reception) too low | " Le salaire journalier à temps complet ne peut pas être inférieur à X SMIC." (and `temps partiel`) |
| `ERR_METIER` | AF: particular-constraint days above 31.5 / SMIC/day out of range | " Le nombre de jours de sujétions particulières ne peut être supérieur à 32." / " Pour les sujétions particulières le nombre de smic net par jour ne peut être inférieur à XX et supérieur à YY" |
| `ERR_METIER` | AF: maintenance-allowance days above 31.5 / guaranteed minimum out of range | " Le nombre de jours d'indemnités d'entretien ne peut être supérieur à 32." / " Pour l'indemnité d'entretien le nombre de minimum garanti par jour ne peut être inférieur à XX et supérieur à YY" |
| `ERR_METIER` | total net above 99 999,99 €, or calculation engine error | "Une erreur s'est produite durant la simulation des cotisations." |
| `ERR_TECHNIQUE` | HTTP 500 | "Une erreur système est survenue" |

Discrepancy (PDF, 2.0.1): the Swagger `congesPayes` text says `heureCp` where the field is `heuresCp`.

Fixtures: `estimate_request.json` (PDF curl body), `estimate_response.json`, `error_param_invalide.json` (shape).

### 2.7 CESU050 — Declare a salary — `POST /employeurs/{numeroCesu}/declarer`

`operationId`: `declarerSalaireCesu`. Tag `Au sujet des PE`. Declares the salary paid by an employer to an employee, as a
standard Cesu declaration (`typeDeclaration = 0`) or a **Cesu+ declaration with AICI** (`typeDeclaration = 1`). The
platform must hold the employer's mandate (§1.4).

Effects of a successful call (PDF §3.7.1): the declaration is stored; a letter or e-mail confirms receipt through the
third-party declarant; a **payslip** is generated for employer and employee (post and/or online space); URSSAF debits the
employer on the due date for the contributions and, where applicable, the withholding tax due by the employee; with
Cesu+ and AICI it also debits the salary remaining at the employer's charge and pays the employee the salary net of income
tax. AICI (PDF §3.7.2) is computed on the salary, the contributions and the PAS due, applied **first to contributions,
then to PAS, then to salary**; the employer is debited for what remains under the Cesu+ rules. Deposit (*acompte*,
§3.7.3): must not exceed the declared **net** salary minus the PAS due.

Path parameter: `numeroCesu` (string, required, exactly 14 chars, employer Cesu number).

Body: `InputDeclaration` (required: `dateDebut`, `dateFin`, `dateVersement`, `infosSalarie`)

| Field | Type | Req. | Rules |
|---|---|---|---|
| `dateDebut` | string (date) | yes | start of the service, `YYYY-MM-DD` (example `2019-01-01`); not after today, not after `dateFin` |
| `dateFin` | string (date) | yes | end of the service; must be in the **same month** as `dateDebut` |
| `dateVersement` | string (date) | yes | payment date of the salary |
| `typeDeclaration` | integer (int32) | no | `0` = Cesu declaration (default); `1` = Cesu+ declaration with AICI (§3.3) |
| `natureActivite` | string | cond. | activity nature code, §3.12. Swagger: exactly 2 chars; PDF: 2–3 chars. **Required** for `typeDeclaration = 1`; optional for `typeDeclaration = 0`; must not be set for `AF` |
| `montantAcompte` | number (double) | no | salary already paid by the employer to the employee; Swagger minimum 0.01 (PDF: no minimum) |
| `revenusAf` | `RevenusAf` | no | foster-carer income, see below |
| `revenusEf` | `RevenusEf` | no | employee-type income, see below |
| `infosSalarie` | `InfosSalarie` | yes | the employee, see below |

`RevenusEf` = `RevenusEfBase` (§2.6, without `typeSalaire`, `codeExoneration`, `codeZoneGeographique`, `congesPayes`) +
`periodesCongesPayes`. The exemption code is **not** sent on declaration: URSSAF applies the one stored for the employer
(supporting documents, department APA/PCH, automatic exemption above 70).

| Field | Type | Req. | Rules |
|---|---|---|---|
| `salaireHoraire` | number (double) | yes | max 999.99, multiple of 0.01 |
| `heuresTravaillees` | integer (int32) | yes | 0–200 |
| `nbHeureSupplementaire1` | integer | no | 0–35 (+25 %; only if normal hours ≥ 40) |
| `nbHeureSupplementaire2` | integer | no | 0–40 (+50 %; only if ≥ 8 hours at +25 %) |
| `heuresCp` | integer | cond. | 0–200; paid-leave hours, to give when paid leave is managed separately (`congesPayes = false`) and at least one paid-leave period is declared; forbidden when `congesPayes = true` |
| `complementsSalaire` | array of `ComplementSalaire` | no | salary supplements |
| `periodesCongesPayes` | array of `PeriodeCongePaye` | no | paid-leave periods |

`ComplementSalaire` (`codeCategorie` and `codeSousCategorie` required by the Swagger; **`montant` also required in PDF
v2.0.2**, "complément non pris en compte sinon"):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `codeCategorie` | string | yes | exactly 4 chars, §3.8 (`HEUR`, `AVAN`, `PART`, `PRIM`, `TRAN`, `FINC`) |
| `codeSousCategorie` | string | yes | exactly 4 chars, §3.8 (the pair must exist: `PARAM_INVALIDE` otherwise) |
| `montant` | number (double) | yes (PDF) | max 99999.99, multiple of 0.01 |
| `salaireHoraire` | number (double) | no | max 999.99, multiple of 0.01; for some supplements (specific hours) |
| `nombreHeures` | integer (int32) | no | 0–999; for some supplements |

`PeriodeCongePaye`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `dateDebut` | string (date) | no | start of the leave (example `2019-01-02`); not after `dateFin`; periods must not overlap and must be inside the employment period |
| `dateFin` | string (date) | no | end of the leave |
| `nbJoursCP` | number (double) | no | 0–31, multiple of 0.5; every period needs it |

`RevenusAf` (also reused by `RevenusAfSimu`, §2.6; all optional, numbers are doubles):

| Field | Rules |
|---|---|
| `montantIndemniteLogement` | min 0; housing allowance amount |
| `nbJoursIndemniteEntretien` | 0–99, multiple of 0.5; maintenance-allowance days |
| `nbJoursSujetionsParticulieres` | 0–99, multiple of 0.5; particular-constraint days |
| `nbJoursAccueilTempsComplet` | 0–99, multiple of 0.5; full-time reception days |
| `nbJoursAccueilTempsPartiel` | 0–99, multiple of 0.5; part-time reception days |
| `nbMinimumGarantiJourIndemniteEntretien` | 2–5; guaranteed minimum (gross)/day for the maintenance allowance |
| `nbSmicsJourSujetionsParticulieres` | min 0; SMIC/day for particular constraints |
| `nbSmicsJourAccueilTempsComplet` | min 0; SMIC/day for full-time reception |
| `nbSmicsJourAccueilTempsPartiel` | min 0; SMIC/day for part-time reception |

`InfosSalarie` (nothing is `required` by the Swagger; the rules come from PDF §3.7.5 and §3.7.4):

| Field | Type | Req. | Rules |
|---|---|---|---|
| `numeroCesu` | string | cond. | exactly 14 chars; **cannot be used for the first declaration of an employer/employee pair** (administrative data must be sent so that URSSAF can match an existing employee) |
| `codeCivilite` | string | cond. | `"1"` / `"2"`, §3.1; required without `numeroCesu` |
| `adresse` | `Adresse` | cond. | §2.1 address; required without `numeroCesu` |
| `nomNaissance` | string | cond. | max 30, pattern as `nom` (§2.1); required without `numeroCesu` |
| `nomUsage` | string | no | max 30, same pattern |
| `prenom` | string | cond. | max 30, same pattern; required without `numeroCesu` |
| `dateNaissance` | string (date) | cond. | required without `numeroCesu` and without `nir`; optional with `nir` |
| `villeNaissance` | string | cond. | max 30, same pattern; required without `numeroCesu` and `nir`; optional with `nir` |
| `departementNaissance` | string | cond. | max 3 chars; required only when `codePaysNaissance` = `100` |
| `codePaysNaissance` | string | cond. | max 3 chars (INSEE country code, France = `100`); required without `numeroCesu` |
| `email` | string | no | max 256, same pattern as §2.1 |
| `telephone` | string | no | exactly 10 chars; pattern `^$\|.*[0-9]{10}.*` |
| `nir` | string | cond. | exactly 15 chars; alternative to `numeroCesu`; when set, only the administrative fields marked required above must follow |
| `typeEmploi` | string | cond. | `PE` or `AF`, §3.2; required without `numeroCesu` (error "le type d'emploi est obligatoire") and, per the PDF table, always |
| `congesPayes` | boolean | cond. | `true` = 10 % paid leave included in the hourly wage (then `heuresCp` and `periodesCongesPayes` must not be sent); `false` = not included; **required on the first declaration of the pair**, optional afterwards |
| `agrementAF` | `AgrementAF` | cond. | required when `typeEmploi = AF`; see below |

With `numeroCesu` set, only `typeEmploi` and `congesPayes` may accompany it (PDF §3.7.5 1/). Employee matching on the
URSSAF side (PDF §3.7.4): search by employee number among the employer's employees (those with a contract); otherwise by
civil status among the employer's employees, then among all employees, on **birth name + birth year/month + postal code**,
refined with first name and usage name; if duplicates remain URSSAF asks for the NIR; if nobody matches, the employee and
the contract are created; an inactive matching contract is reactivated.

`AgrementAF` (all optional in the schema; conditional rules from PDF §3.7.5 4/):

| Field | Type | Rules |
|---|---|---|
| `codeConseilDepartemental` | string | exactly 3 chars, §3.11; required when `indCertificationValiditeAgrement` is `true` |
| `numeroAgrement` | string | max 20 |
| `dateDebutAgrement` | string (date) | required when `indCertificationValiditeAgrement` is `true` |
| `dateFinAgrement` | string (date) | same |
| `indCertificationValiditeAgrement` | boolean | the foster carer holds a valid approval; if `true`, `indRemplacementPonctuel` must be `false` |
| `indRemplacementPonctuel` | boolean | no approval, occasional replacement; if `true`, `indCertificationValiditeAgrement` must be `false`. One of the two indicators must be `true` |

The PDF curl body is the Swagger-UI template: it holds `"string"` placeholders and zeros (for instance `numVoie: 0`,
`typeEmploi: "PE"` with an `agrementAF`) that **violate the contract**; it is kept verbatim only as a shape sample.

Response 200: `OutputDeclaration` (the PDF gives no example; amounts are in euros, doubles)

| Field | Type | Rules |
|---|---|---|
| `idDeclaration` | string | declaration document reference, 12 characters (example `209299542888`) |
| `montantsCotisations` | `Cotisations` | `cotisationsPatronales` / `cotisationsSalariales` (totals) |
| `montantPAS` | number | computed withholding at source |
| `montantPrelevementPECotisations` | number | contributions left at the employer's charge, after aid (immediate tax credit, department aid) |
| `montantPrelevementPEPAS` | number | PAS left at the employer's charge, after aid (immediate tax credit) |
| `montantPrelevementPESalaire` | number | salary left at the employer's charge, after aid (immediate tax credit, department aid) |
| `montantSalaireBrut` | number | gross salary reconstituted from the net |
| `montantSalaireNetImposable` | number | net taxable salary |
| `montantVirementSalaire` | number | net salary paid by the CNCESU to the employee; **Cesu+ only** |
| `montantAICI` | number | total AICI applied to contributions, PAS and salary |

Errors (HTTP 400, PDF §3.7.7). Generic and mandate-related:

| `code` | Condition | `message` / `description` |
|---|---|---|
| `PARAM_INVALIDE` | generic | "Le paramètre typeEmploi est invalide" (example) |
| `MANDAT_INEXISTANT` | no third-party mandate (§1.4) | "Mandat de tierce déclaration absent" / `null` |
| `RIB_ABSENT` | no employer RIB | "Absence de RIB" / `null` |
| `PARAM_INVALIDE` | missing `codeCivilite` / `nomNaissance` / `prenom` / `codePaysNaissance` (invalid) / `typeEmploi` without `numeroCesu`; bad `lettreVoie` / `typeVoie` | "Erreur sur le champ <field> : …" (verbatim texts in the PDF) |
| `PARAM_INVALIDE` | unknown supplement | "Erreur sur le champ complementsSalaire : le complément de salaire de catégorie XX et de sous-catégorie YY n'existe pas." |
| `AICI_NON_ACTIVE` | Cesu+ declaration while AICI is not active | "Service d'AICI non activé sur le compte en ligne employeur" |
| `NATURE_INCORRECTE` | nature incompatible with Cesu+ and AICI | "Nature d'activité incompatible" |
| `NATURE_INCORRECTE_AF` | `AF` declaration with a nature | "Nature d'activité incompatible" (description truncated in the PDF: "…à une nature d'activi") |
| `NATURE_INCONNUE` | unknown nature code | "Nature d'activité inconnue" |
| `NATURE_ABSENTE` | nature missing | " Nature d'activité non saisie" |
| `ACOMPTE_ELEVE` | deposit too high | "Montant d'acompte déclaré trop élevé" |

`ERR_METIER` (description is the discriminator; message " Une erreur métier est survenue"):

| Condition | `description` (verbatim) |
|---|---|
| paid leave while 10 % included (`congesPayes = true`) | "La déclaration réalisée est soumise à la règle des congés inclus de 10% (congesPayes = true). Les informations heures de CP (heuresCp) et/ou périodes de congés payés (periodesCongesPayes) ne doivent donc pas être renseignées." |
| duplicate (same employer/employee pair and same employment dates) | "Une déclaration est déjà enregistrée pour cette période d’emploi." |
| start date after today | "La date de début d'emploi doit être antérieure ou égale à la date du jour." |
| start date after end date | "La date de début d'emploi doit être inférieure ou égale à la date de fin." |
| period over several months | "La période d'emploi ne peut pas s'étaler sur plusieurs mois." |
| PE: no hours, no supplements / paid leave | "Veuillez indiquer un nombre d'heures effectuées." |
| PE: total net above 99 999,99 € | "Le total net déclaré est incohérent." |
| PE: net hourly wage above 999,99 € | "Le salaire horaire net est incohérent." |
| PE: net hourly wage below SMIC | "Le salaire horaire net ne peut être inférieur à XX,XX €." |
| PE: overtime with < 40 normal hours; 50 % overtime with < 8 hours at 25 % | same texts as §2.6 |
| PE: paid-leave period without leave hours / leave days without period / start after end / overlap / outside employment period / leave hours without days | "Le nombre d'heures déclarées au titre des congés payés doit être supérieur à 0." / "Toutes les périodes de congés payés doivent être renseignées." / "Veuillez indiquer des dates de début de période de congés antérieures ou égales aux dates de fin." / "Les périodes de congés payés déclarées se chevauchent." / "Les congés payés doivent être inclus dans la période d'emploi." / " Veuillez indiquer un nombre total de jours de congés pris supérieur à 0." |
| PE: specific-hours wage too low; employee participation / benefits-in-kind rules | as §2.6, plus "Le montant de la participation financière du salarié doit être inférieur au cumul des avantages en nature." and "…doit être inférieur à la rémunération déclarée hors avantage en nature." |
| PE: hours given without hourly wage | "Des heures ont été saisies, vous devez saisir un salaire horaire net." |
| PE: Cesu+ without nature | "Veuillez sélectionner une nature d'activité." |
| AF rules | same texts as §2.6; plus "Il n'est pas possible de réaliser de déclaration pour les accueillants familiaux via l'API" |
| calculation engine error | "Une erreur s'est produite durant la simulation des cotisations." |

`ERR_METIER` with message **"Une erreur métier cesu+ est survenue"** (Cesu+ eligibility, one `description` each):
"Pas de coordonnées bancaires actives sur le compte de l'employeur…"; "Anomalie compte en ligne employeur…";
"Anomalie compte employeur. Veuillez contacter le Centre National Cesu." (twice); "Service Cesu+ désactivé à la demande de
l'employeur…"; "Présence d'un impayé sur salaire sur le compte employeur…"; "Présence d'un impayé sur les cotisations ou
prélèvement à la source sur le compte employeur…"; "Pas de coordonnées bancaires actives sur le compte du salarié…";
"Anomalie sur le compte en ligne salarié…"; "Anomalie compte salarié. Veuillez contacter le Centre National Cesu";
"XXXXXX" (placeholder in the PDF); "Service Cesu+ désactivé…"; "Coordonnées bancaires employeur et salarié identiques…";
"Etat civil employeur et salarié identique…"; "Anomalie sur le couple employeur / salarié."; "Délai de saisie de déclaration
Cesu+ dépassé. La réception de la déclaration ne doit pas être après le 15 du mois qui suit le mois déclaré. Il est cependant
toujours possible de réaliser une déclaration Cesu socle."; "Plafond de salaire net déclaré dépassé : il n'est pas possible de
réaliser une déclaration cesu+ avec un salaire supérieur à 6000 € pour une période donnée."

`ERR_TECHNIQUE` (500): "Une erreur système est survenue".

Discrepancies: `natureActivite` length (2 vs 2–3); `montantAcompte` minimum; `ComplementSalaire.montant` required only
in the PDF; Swagger text "heureCp" for `heuresCp`; AF accepted by the schema but rejected by the service.

Fixtures: `declare_request_placeholder.json` (PDF curl body, verbatim, contract-violating placeholders),
`declare_response_assembled.json` (assembled), `error_mandat_inexistant.json`, `error_err_metier_cesuplus_unpaid_salary.json`,
`error_technical.json`.

### 2.8 CESU060 — Activate Cesu+ — `POST /employeurs/{numeroCesu}/cesuplus`

`operationId`: `activationCesuPlus`. Tag `Au sujet des PE`. Activates the Cesu+ service between an employer and an
employee (the service that lets URSSAF pay the employee). No request body. Mandate required (§1.4).

| Field | In | Type | Req. | Rules |
|---|---|---|---|---|
| `numeroCesu` | path | string | yes | employer Cesu number, exactly 14 chars (the PDF table says "Query" but the URL carries it in the path) |
| `dateNaissance` | query | string (date) | yes | employer birth date `YYYY-MM-DD` |
| `numeroCesuSalarie` | query | string | yes | employee Cesu number, exactly 14 chars (PDF example `00000123456789`) |
| `accord` | query | boolean | yes | employee's agreement given to the employer (`false` = not agreed, `true` = agreed). The PDF describes `0` / `1` and its example uses `accord=1` |

PDF example: `POST …/employeurs/Z0003752390009/cesuplus?dateNaissance=1975-10-01&numeroCesuSalarie=00000123456789&accord=1`.

Response 200: `OutputActivationCesuPlus`

| Field | Type | Rules |
|---|---|---|
| `activationCesu` | boolean | Cesu+ is active for the employer/employee pair (`false` = not active) |

Errors (HTTP 400):

| `code` | Condition | `description` (verbatim) |
|---|---|---|
| `PARAM_INVALIDE` | generic | "Le paramètre dateNaissance est invalide" (example) |
| `MANDAT_INEXISTANT` | no mandate | `null` (message "Mandat de tierce déclaration absent") |
| `ERR_METIER` | employer number / birth date pair unknown | "N° CESU du particulier employeur inconnu." |
| `ERR_METIER` | no agreement from the pair | "Absence de l'accord du couple particulier employeur / salarié." |
| `ERR_METIER` | employee number unknown | "N° CESU du salarié inconnu." |
| `ERR_METIER` | no contract between the two | "Aucun contrat existant pour le couple particulier employeur / salarié." |
| `ERR_METIER` | already active | "CESU+ déjà activé pour le couple PE/salarié." |
| `ERR_METIER` | blocking reason on the employer / employee / pair | "Blocage Cesu+ pour le particulier employeur." / "Blocage Cesu+ pour le salarié." / "Blocage Cesu+ pour le couple particulier employeur/salarié." |
| `ERR_METIER` | employer exempted under APA/PCH | "Employeur ayant une exonération APA/PCH." |
| `ERR_TECHNIQUE` | HTTP 500 | "Une erreur système est survenue" |

Fixtures: `activate_cesu_plus_response.json`, `error_mandat_inexistant.json`, `error_param_invalide.json` (shape).

### 2.9 CESU070 — Activate AICI — `POST /employeurs/{numeroCesu}/aici`

`operationId`: `activationAici`. Tag `Au sujet des PE`. Activates the immediate tax credit advance for an employer. URSSAF
checks the person with the tax administration (DGFiP R2P and ImpotPart APIs). No mandate error is documented for this call.

Path parameter: `numeroCesu` (string, required, exactly 14 chars; the PDF table says "Query").

Body: `InputActivationAici` (required: `codeCivilite`, `codePaysNaissance`, `dateNaissance`, `nomNaissance`, `nomUsage`, `prenom`)

| Field | Type | Req. | Rules |
|---|---|---|---|
| `nomNaissance` | string | yes | max 30 (no pattern here, unlike `InputInscriptionPe.nom`) |
| `nomUsage` | string | yes | max 30 (required even without a usage name: handling of an empty value is not published) |
| `prenom` | string | yes | max 30 |
| `codeCivilite` | string | yes | 1–2 chars; `1` = masculin, `2` = féminin (no enum in the schema, unlike §2.1) |
| `dateNaissance` | string (date) | yes | employer birth date `YYYY-MM-DD` (example `2019-01-30`) |
| `codePaysNaissance` | string | yes | exactly 3 chars, INSEE country code (France = `100`) |
| `codeCommuneNaissance` | string | cond. | exactly 5 chars, INSEE municipality code (example `69381`); required when `codePaysNaissance` = `100` |
| `libelleCommuneNaissance` | string | cond. | max 60 (example `LYON 01`); required when `codePaysNaissance` = `100` (error text mentions "code commune et/ou libellé") |

The PDF gives no request example for this call, so `activate_aici_request_assembled.json` is assembled.

Response 200: `OutputActivationAici`

| Field | Type | Rules |
|---|---|---|
| `activationAici` | boolean | AICI is active. The Swagger description is a copy of the Cesu+ one ("Accord du SP auprès du PE pour l'activation du Cesu+"); the PDF title is "Indicateur d'activation de l'avance immédiate de crédit d'impôt" |

Errors (HTTP 400):

| `code` | Condition | `description` (verbatim) |
|---|---|---|
| `PARAM_INVALIDE` | generic | "Le paramètre dateNaissance est invalide" (example) |
| `ERR_METIER` | employer unknown | "Aucun employeur n'a été trouvé avec ces informations." |
| `ERR_METIER` | `codeCommuneNaissance` missing for France | "Le paramètre codeCommuneNaissance est manquant." |
| `ERR_METIER` | not recognised by DGFiP R2P | "Le particulier n'a pas été reconnu par l'API DGFIP R2P." |
| `ERR_METIER` | APA/PCH exemption, or DGFiP ImpotPart refusal | "Le particulier n'est pas éligible au service d'AICI." |
| `ERR_METIER` | Cesu+ not active | "Le particulier n'a pas le service cesu+ activé." |
| `ERR_TECHNIQUE` | HTTP 500 | "Une erreur système est survenue" |

Fixtures: `activate_aici_request_assembled.json`, `activate_aici_response.json`, `error_param_invalide.json` (shape).

---

## 3. Reference values

### 3.1 Civility (`codeCivilite`)

`"1"` = M., `"2"` = Mme. Strings. (`InputActivationAici.codeCivilite` has no enum but the same meaning.)

### 3.2 Employment type (`typeEmploi`)

`PE` (emploi familial, regular private employer), `AF` (accueillant familial).

### 3.3 Declaration type (`typeDeclaration`)

Integer: `0` = Cesu declaration (default), `1` = Cesu+ declaration with AICI.

### 3.4 States (CESU020)

| Field | Values |
|---|---|
| `etatCompteEnLigne` | `absent`, `inactif`, `actif`, `bloque`, `radie` |
| `etatMandatSepa` | `absent`, `inactif`, `actif` |
| `etatCesuPlus` | Swagger: `active`, `bloque`, `desactive`. PDF: `activé`, `bloqué`, `désactivé`. The package accepts both spellings (§4); unknown values are preserved raw |

### 3.5 Salary type (`typeSalaire`)

`BRUT`, `NET`.

### 3.6 Exemption codes (`codeExoneration`, simulation only)

| Code | Label (PDF §4.1) |
|---|---|
| `02` | Aucune exonération |
| `01` | EXONERE + 70 ANS |
| `03` | AEEH |
| `05` | INVALIDITE 80% |
| `06` | RECOURS TIERCE PERSONNE |
| `70` | PRESTATION COMPENSATION DU HANDICAP |
| `71` | PRESTATION COMPENSATION DU HANDICAP ENFANT |
| `80` | ALLOCATION PERSONNALISEE D'AUTONOMIE |

The Swagger carries no enum for this field (2 chars).

### 3.7 Geographic zones (`codeZoneGeographique`)

| Code | Label | Source |
|---|---|---|
| `NATIO` | Métropole (hors Bas-Rhin, Haut-Rhin et Moselle) | PDF + Swagger |
| `97100` | Outre-mer | PDF + Swagger |
| `ALSMO` | Bas-Rhin, Haut-Rhin et Moselle | PDF + Swagger |
| `DOM`, `ETRAN`, `97200`, `97300`, `97400`, `97700`, `97800` | label not published | Swagger enum only |

### 3.8 Salary supplements (`ComplementSalaire`)

Categories (`codeCategorie`): `HEUR` Heures spécifiques, `AVAN` Avantages en nature, `PART` Participation salariée,
`PRIM` Prime, `TRAN` Transport, `FINC` Fin de contrat.

Sub-categories (`codeSousCategorie`):

| Code | Label | Category |
|---|---|---|
| `NUIT` | Heures de présence de nuit | `HEUR` |
| `GARD` | Heures de garde-malade de nuit | `HEUR` |
| `RESP` | Heures de présence responsable | `HEUR` |
| `NOUR` | Nourriture | `AVAN` |
| `LOGE` | Logement | `AVAN` |
| `AUTR` | Autres | `AVAN` |
| `PART` | Participation salariée | `PART` |
| `PRIM` | Prime | `PRIM` |
| `KILO` | Indemnités kilométriques | `TRAN` |
| `FRAI` | Frais de transport | `TRAN` |
| `LICE` | Indemnités de licenciement, Mise à la retraite | `FINC` |
| `RETR` | Indemnité compensatrice de préavis, congés payés, prime de précarité, fin anticipée de CDD | `FINC` |

Neither list is an enum in the Swagger (4 chars each).

### 3.9 Street letters (`lettreVoie`)

`A` … `Z` (single letter). Notable meanings (PDF §4.5): `B` = B ou Bis, `C` = C ou Quinquies, `Q` = Q ou Quater, `T` = T ou Ter.
All other letters stand for themselves.

### 3.10 Street types (`typeVoie`) — 33 values

| Code | Label | Code | Label | Code | Label |
|---|---|---|---|---|---|
| `ALL` | Allée | `AV` | Avenue | `BAT` | Batiment |
| `BD` | Boulevard | `CAN` | Canal | `CHS` | Chaussée |
| `CHE` | Chemin | `CI` | Cité | `CL` | Clos |
| `C` | Cours | `COG` | Cottage | `FG` | Faubourg |
| `HAM` | Hameau | `IMB` | Immeuble | `IMP` | Impasse |
| `LOT` | Lotissement | `MAI` | Maison | `MTE` | Montée |
| `PAS` | Passage | `PAR` | Parc | `PAV` | Pavillon |
| `PL` | Place | `POR` | Porte | `Q` | Quai |
| `QU` | Quartier | `RAM` | Rampe | `RES` | Résidence |
| `RTE` | Route | `R` | Rue | `RUL` | Ruelle |
| `SEN` | Sentier | `SQ` | Square | `VO` | Voie |

This list is much shorter than the one of the Tiers de prestations API (do not reuse it).

### 3.11 Departmental councils (`codeConseilDepartemental`) — 101 values

3 characters, label `CONSEIL GENERAL <département>` (PDF §4.7; the PDF calls the field `codeConseulDepartemental`):
`001` to `095` **except `020`**, with Corsica as `02A` (CORSE-DU-SUD) and `02B` (HAUTE-CORSE), then `971` GUADELOUPE,
`972` MARTINIQUE, `973` GUYANE, `974` LA REUNION, `976` DE MAYOTTE (there is no `975`). Examples: `001` AIN, `013`
BOUCHES-DU-RHONE, `069` RHONE, `075` PARIS. The full list is in the PDF (pages 79 to 83); the Swagger has no enum. Only
needed for `AF`, which the API refuses at declaration time (§1.2).

### 3.12 Activity natures (`natureActivite`)

2-character codes (PDF §4.8), required for Cesu+ declarations:

| Code | Label |
|---|---|
| `03` | Entretien quotidien et travaux ménagers du domicile |
| `04` | Livraison de course ou de linge à domicile |
| `05` | Assistance administrative à domicile |
| `06` | Soutien scolaire ou cours à domicile |
| `07` | Gardiennage et entretien du domicile |
| `08` | Petit jardinage |
| `09` | Petit bricolage |
| `10` | Assistance informatique à domicile |
| `11` | Aide et assistance aux personnes agées ou handicapées |
| `12` | Aide et assistance aux personnes temporairement dépendantes |
| `13` | Préparation et livraison de repas à domicile aux personnes âgées ou handicapées |
| `14` | Accompagnement ou conduite du véhicule de personnes agées ou handicapées |
| `15` | Accompagnement ou conduite de véhicule pour les personnes temporairement dépendantes |
| `16` | Soins et promenades d'animaux de compagnie aux personnes âgées ou handicapées |
| `17` | Soins esthetiques à domicile aux personnes âgées ou handicapées |
| `18` | Interprète auprès de personnes sourdes ou malentendantes |
| `19` | Téléassistance aux personnes âgées ou handicapées ou isolées |
| `20` | Accompagnement et garde d'enfant |
| `21` | Accompagnement et garde d'enfant handicapé |
| `22` | Autres activités ou services |

Which natures are compatible with Cesu+ and AICI is not published (`NATURE_INCORRECTE` is the only signal; the Tiers de
prestations list uses a different coding, do not reuse it).

### 3.13 Error code catalogue

| Code | HTTP | Operation(s) | Meaning |
|---|---|---|---|
| `PARAM_INVALIDE` | 400 | all | invalid or missing parameter; `description` names the field; message "Un des paramètres de votre demande est invalide" or "La requête est incorrecte" |
| `ERR_METIER` | 400 | 010, 020, 040, 050, 060, 070 | generic business error, discriminated by `description`; message " Une erreur métier est survenue" or, for Cesu+ checks of 050, "Une erreur métier cesu+ est survenue" |
| `SEPA_ABSENT` | 400 | 010 | no SEPA mandate authorisation |
| `MANDAT_INEXISTANT` | 400 | 050, 060 | no third-party mandate |
| `RIB_ABSENT` | 400 | 050 | employer RIB missing |
| `COMPTE_DEJA_MANDATE` | 400 | 030, 031 | mandate held by another establishment |
| `ER_API_020_0000` | 400 | 030, 031 | no Cesu account found |
| `AICI_NON_ACTIVE` | 400 | 050 | Cesu+ declaration while AICI is not active |
| `NATURE_INCORRECTE` | 400 | 050 | nature incompatible with Cesu+ and AICI |
| `NATURE_INCORRECTE_AF` | 400 | 050 | `AF` declaration cannot carry a nature |
| `NATURE_INCONNUE` | 400 | 050 | unknown nature |
| `NATURE_ABSENTE` | 400 | 050 | nature missing |
| `ACOMPTE_ELEVE` | 400 | 050 | deposit above the allowed amount |
| `ERR_TECHNIQUE` | 500 | 010, 020, 021, 040, 050, 060, 070 | technical error, retryable (no body semantics beyond the text) |
| `ERREUR_TECHNIQUE` | 500 | 030, 031 | technical error (other spelling) |
| — | 401, 403, 512 | all | security errors (token absent / invalid, access refused); no documented body |
| — | 429 | all | quota exceeded (PDF); no documented body |

---

## 4. Package decisions derived from the specification

1. **Date encoding.** Every date is date-only `YYYY-MM-DD` (birth dates, employment period, payment date, leave periods,
   approval dates), modelled as a calendar-date value object without time zone. Rules that compare with "today"
   (employment start not in the future, Cesu+ deadline on the 15th of the following month) are checked locally in a
   configurable business time zone, default `Europe/Paris` (*TBD*: URSSAF's reference time zone is not published).
   Do not reuse the date-time encoding of the Tiers de prestations package.
2. **Money and quantities.** Amounts (hourly wages, supplements, deposit, estimates and declaration results) are integers
   in cents internally, encoded as JSON numbers with at most two decimals and parsed from responses without float drift
   (e.g. `63.1`, `20.09`). Day counts are multiples of 0.5 (stored as half-days); hours are integers. Local checks
   replicate the contract bounds (§2); SMIC-related and overtime business rules stay server-side (*TBD*: cheap local
   pre-checks for date order, same month, 40 h / 8 h overtime thresholds and the 10 % paid-leave exclusivity).
3. **Statuses and states.** State fields of 020 (`etatCompteEnLigne`, `etatMandatSepa`, `etatCesuPlus`) are string enums
   with an `Unknown(raw)` fallback; `etatCesuPlus` accepts both `active|bloque|desactive` and `activé|bloqué|désactivé`.
   `motifBlocageCesuPlus` is read from either casing and the string `"null"` is treated as absent. No declaration has a
   status endpoint: declarations are **not** a state machine on the URSSAF side.
4. **Local lifecycle (tracking).** `employer registered` (010) → `mandate notified` (030) → `mandate confirmed` when 020
   returns `mandatPresent = true` → `Cesu+ active` (060 / `etatCesuPlus`) → `AICI active` (070 / `etatAICI`).
   031 moves back to `mandate cancelled`. State is re-read with 020 rather than trusted from a past write (one platform
   at a time, the employer can change things on cesu.urssaf.fr).
5. **Idempotency and retries.** There is no idempotency key and no GET/cancel for declarations. The only guard is the
   server-side duplicate rule (same employer, employee and employment dates → `ERR_METIER` "Une déclaration est déjà
   enregistrée pour cette période d’emploi."). Therefore: 050 is **never retried automatically**; on timeout or 5xx the
   outcome is *unknown* and surfaced as such; a later duplicate error is read as "already declared" (the `idDeclaration` of
   the first call may be lost) and is reconciled by the caller. 429 is retried with back-off (no `Retry-After` documented).
   010 is also not retried blindly (e-mail uniqueness). 020, 021, 030, 031, 040 are safe to retry (030/031 *assumed*,
   see §5).
6. **Error mapping.** Keep `code`, `message` and `description` raw. Map well-known codes (§3.13) to typed exceptions;
   `ERR_METIER` is classified by a best-effort table of known `description` texts (texts contain placeholders such as
   `XX,XX` and may change), with the raw error always available. Both technical spellings (`ERR_TECHNIQUE`,
   `ERREUR_TECHNIQUE`) are treated as retryable technical errors for reads.
7. **Employee identification.** Value object with three mutually exclusive modes: Cesu number, NIR, or administrative
   data (names with the PDF character set, birth date, birth place, country `100` rule); validated locally per §2.3 and
   §2.7. `numeroCesu` is refused locally on a first declaration only if the package knows the pair is new (otherwise
   left to the API).
8. **Country, department, address codes.** Country code is **3 chars** (`100` = France, not the 5-digit `99100` of
   Tiers de prestations); department is free text of up to 3 chars (`07`, `972`); street types are the 33 values of §3.10.
   `Adresse` enforces the 32-character line rule and the conditional road fields locally.
9. **`accord` query parameter (060).** Sent as `true` / `false` as the Swagger types it (TBD: confirm against the sandbox,
   since the PDF documents `0` / `1` and its example uses `accord=1`).
10. **Mandate.** `mandat` is a dedicated aggregate (employer number, birth date, platform, dates of 030/031, last
    `mandatPresent`). The package never sends 050/060 for an employer without a locally known mandate unless the caller
    opts out (the API reports `MANDAT_INEXISTANT` anyway).
11. **Cesu / Cesu+ choice.** `typeDeclaration` is derived from an explicit declaration kind in the domain
    (`StandardDeclaration`, `CesuPlusDeclaration`); `CesuPlusDeclaration` requires a nature code. `AF` declarations are
    modelled (the contract has them) but flagged unsupported by the API (§1.2); the package fails fast locally unless
    the caller opts in.
12. **Personal and banking data.** `nir`, names, birth dates, e-mail, phone, address, `iban`, `bic` are personal data:
    never logged, masked in exceptions. IBAN/BIC are only structurally pre-checked (length), the real checks are
    server-side.
13. **Shared model.** CESU, PAJE and AE share `/tiersdecl/v1` and the mandate model; a shared library may be extracted
    later, out of scope here.

---

## 5. Open points

Discrepancies Swagger v2.0.0 / PDF v2.0.2 (prd and sb contracts are otherwise identical):

1. `etatCesuPlus`: `active` / `bloque` / `desactive` (Swagger) vs `activé` / `bloqué` / `désactivé` (PDF, including its
   example). To confirm on the sandbox; the package accepts both meanwhile.
2. `motifBlocageCesuPlus` (Swagger) vs `motifblocageCesuPlus` (PDF); the PDF example holds the string `"null"`.
3. Name patterns (`nom`, `prenom`, `nomNaissance`, `nomUsage`, `villeNaissance`) are ASCII-folded and degenerate in the
   Swagger JSON; the accented PDF pattern is used. The PDF also prints the model name as `Rib` / fields as `Inscrit`,
   `NirSala`, `PrenomSala`, `Nir`, `Accord` (capitalisation typos; the Swagger names are used).
4. `natureActivite`: max length 2 (Swagger) vs 3 (PDF); `montantAcompte` minimum 0.01 only in the Swagger;
   `ComplementSalaire.montant` required only in PDF v2.0.2 (Swagger requires `codeCategorie` and `codeSousCategorie`);
   `Adresse.codePostal` mandatory in the PDF text only; `InputActivationAici.codeCivilite` has length 1–2 and no enum while
   `codeCivilite` elsewhere is the enum `["1","2"]`; `telephone` has a 10-char minimum on `InfosSalarie` only.
5. `codeZoneGeographique`: 10 values in the Swagger enum, 3 in PDF §4.2 (labels of the 7 others unknown).
6. 060 / 070: the PDF table says `numeroCesu` is a query parameter, the path is `/employeurs/{numeroCesu}/…`; `accord` is `0/1`
   in the PDF (and its example) but `boolean` in the Swagger.
7. 512 is declared by the Swagger on every operation except `/mandats` (030, 031); the PDF lists 401/403/512 for all.
   429 is absent from the Swagger. Technical error code is `ERR_TECHNIQUE` everywhere but `ERREUR_TECHNIQUE` on 030/031.
8. `congesPayes`: the 040 PDF text has a second paragraph saying the flag marks a "gestion spécifique" with `true`
   meaning paid leave **not** included, contradicting the first paragraph and the 050 error text (`true` = 10 % included).
   The package follows the first paragraph and the 050 error (`true` = included). Also "heureCp" vs `heuresCp` (Swagger).
9. 020 and 021: all employee parameters are optional in the Swagger, but the PDF documents a `PARAM_INVALIDE` when no Cesu
   number, NIR or identification data is present, while its 020 example is employer-only. Is an employer-only call valid?
10. `InputActivationAici.nomUsage` is required; behaviour when the employer has no usage name is not published.
11. Swagger `OutputActivationAici.activationAici` description is copied from Cesu+ ("Accord du SP…"); the PDF title is
    clear.
12. PDF `codeConseulDepartemental` (typo for `codeConseilDepartemental`); list has no `975` and uses `02A`/`02B`.
13. PDF example typos: leading space in `" Z0003752390009"` (021), spaces inside key names and trailing comma in the 020 example,
    unquoted keys in the 060 / 070 examples, truncated `NATURE_INCORRECTE_AF` description, `"XXXXXX"` and `XX,XX` placeholders,
    unbalanced quotes in the 020 `ERR_METIER` message. The 050 and 070 requests have no usable official example
    (placeholder template / none) and 050 has no response example: those fixtures are labelled *placeholder* /
    *assembled*.

Behaviour and environment not published:

14. Sandbox: token host (the sb contract still lists `https://api.urssaf.fr/api/oauth/v1/token`), availability of test
    employers / employees, test data, quotas, and whether the sandbox applies the same business controls.
15. Request headers (correlation id) and `Retry-After` on 429; response body of 429 and of 401 / 403 / 512.
16. Whether 010 creates a mandate or only "habilite" the platform (Swagger wording), and whether 030 is still required.
17. Behaviour of re-posting 030 for the same platform, cancelling a non-existent mandate (031), and mandate expiry.
18. Whether `inscrit: false` (200) or an error is returned for an unknown person on 020 / 021; whether `numeroCesu` is returned
    when `inscrit` is false.
19. 400 payload shape: single object only documented; list form (as on other URSSAF APIs) unconfirmed.
20. `ERR_METIER` has no stable sub-code: classification relies on `description` texts (placeholders `XX,XX`, `XXX`, `X SMIC`).
21. AF (`typeEmploi = AF`): schema carries `revenusAf` and `agrementAF`, yet declarations for family foster carers through
    the API are refused; 040 simulation for AF is documented. Real support status to confirm.
22. Which natures (`natureActivite`) are compatible with Cesu+ and AICI (only `NATURE_INCORRECTE` is documented).
23. Formats not specified (only examples): employer Cesu number alphanumeric 14 chars (example `Z0003752390009`, employee
    example `00000123456789`), NIR (15 chars), department codes, `idDeclaration` (12 characters, example numeric).
    Local validation is limited to the published lengths and patterns.
24. Rounding rules of the estimate and declaration amounts, and the SMIC / overtime thresholds referenced by the
    `XX,XX` messages.
25. Whether the first declaration for a pair can reuse a `numeroCesu` obtained through 021 (the PDF forbids it) — to confirm
    on the sandbox.

Assumptions taken by the domain layer (to confirm on the sandbox):

26. Amounts read from responses (PAS, debits, AICI) are modelled as non-negative `Money`; a negative value would fail the
    response mapping (`UnexpectedUrssafResponse`).
27. `etatCesuPlus` accepts the PDF spellings (`activé`, `bloqué`, `désactivé`) in precomposed UTF-8 only (no Unicode
    normalisation, no `intl` dependency), next to the Swagger values.
28. `Coefficient` is limited to 4 decimals: a permissive assumption (no precision is published). Family foster carer
    declarations are refused by the API anyway (§1.2).
29. `montantAcompte` (`Deposit`) must be strictly positive: the Swagger minimum is 0.01, the PDF publishes none (see 4).
30. Formats kept permissive where only lengths are published (see 23): postal and municipality codes are 5 alphanumeric
    characters, country and department codes at most 3 non-space characters, the NIR 15 non-space characters, IBAN 1 to
    34 and BIC 1 to 11 alphanumeric characters (no checksum), `idDeclaration` at most 64 characters.

Assumptions taken by the wire layer (lot 2, to confirm on the sandbox):

31. Nothing is `required` in the Swagger responses (see 18): `inscrit`, `activationCesu` and `activationAici` are required
    (they are the answer), `mandatPresent` and `etatAICI` read as `false` when absent or null (an unregistered employer
    may come without them).
32. Amounts with more than two decimals in responses are rounded to the cent, not refused: a refusal on a successful 050
    would turn a declared salary into an unknown outcome. The rounding rules are not published (see 24).
33. `idDeclaration` is accepted as a JSON integer and converted to its decimal string (the PDF example is numeric and the
    response has no example).
34. Cesu numbers read from responses (010, 021) are trimmed (the PDF 021 example has a leading space, see 13).
35. `agrementAF` is sent whenever present, including with an `infosSalarie` that only carries a `numeroCesu` (the PDF only
    lets `typeEmploi` and `congesPayes` accompany it, but `agrementAF` is required for family foster carers). AF
    declarations are refused by the API anyway (§1.2) and locally unless opted in (ADR 0009).
36. 070 requires `nomUsage` (see 10): an employer without usage name passes the birth name, documented on
    `AiciActivationRequest`. The 070 names use a permissive value object (30 characters, no pattern) rather than the 010
    name pattern, so that no rule is invented.

Assumptions taken by the HTTP gateway (lot 3, to confirm on the sandbox):

37. The sandbox token URL is the sandbox host plus `/api/oauth/v1/token` (`https://api-edi.urssaf.fr/api/oauth/v1/token`),
    although the sb contract still names the production token URL (see 14). `ApiConfiguration` accepts a base URI
    override for the contract tests.
38. No correlation header is sent by default (see 15): a request-id header (name chosen by the caller, value generated per
    attempt) and an application-id header (name and fixed value) are opt-in. `Accept: application/json` is always sent,
    `Content-Type` only when there is a body.
39. A first `401` is answered by one replay with a new token, for the operations that are not safe to retry as well (a `401`
    proves that the call was not processed). `512` is an authentication failure on the API (see 7) and an unavailable
    service on the token endpoint.
40. Every `5xx` other than `512` (listed or not) on 010, 050, 060 and 070 is an unknown outcome: the call reached URSSAF and
    may have been processed (ADR 0003). On the other operations an unlisted `5xx` is an unexpected response. An unlisted
    `1xx`, `3xx` or `4xx` is an unexpected response on every operation.
41. The `accord` parameter of 060 is sent as `true` / `false` by default and as `1` / `0` with
    `AgreementEncoding::Numeric` (see 6, ADR 0008).
