# Review of the pinned DNS provider catalogue

## Scope and evidence

The catalogue describes **acme.sh 3.1.6**, commit
`807da6498377ee5e0cf43a78091f46f12dc59a89`. The generator reads the canonical
Linux/LF source bytes recorded by `UPSTREAM.json`; a Windows checkout with CRLF
is not an interchangeable hashing input.

All 198 `dnsapi/dns_*.sh` metadata assignments and their credential-loading,
authentication-selection and default-handling branches were statically reviewed.
Additional searches covered external executables, local key/configuration files,
interactive authorization, and logging of credential values. No upstream shell
was sourced or executed for this review. **No provider accounts were tested.**
Review coverage is not a claim that every provider API still accepts this pinned
driver or that every driver is eligible in the managed Silesco executor.

The review produces independently worded form rules and short English/Russian
guidance in `resources/provider-overrides.json`. Environment-variable names,
literal defaults and source references are factual interoperability data. The PHP
library does not embed or translate upstream shell implementations. Distribution
of the separate acme.sh runtime retains its own licence and notices; the library's
licence does not replace those requirements.

## Reading the form rules

- A variant is one complete authentication/configuration path. Fields from a
  different variant must not become mandatory just because they exist in the
  provider's overall inventory.
- Optional metadata is not proof that authentication works without any secret.
  Where the source requires one of several secret sets, variants express that
  requirement explicitly.
- `default` is a literal value supplied by the form. In particular, DirectAdmin,
  ISPConfig and KAS need explicit form defaults even though their driver does not
  fill in those advertised values itself.
- Context conditions cover account 2FA, multiple accounts and temporary
  credentials. An unknown external account property is not a reason to pretend
  that the server has verified it; the UI must explain the condition and the
  provider remains authoritative about the account.
- Fields are conservative about secrecy. A non-secret URL, selector or identifier
  is not an executable command or permission to read a host file.
- The `Optional:` heading in Hetzner Cloud supplements `Options:`. The
  `OptionsAlt:` heading is not uniformly an authentication alternative: Baidu,
  Level27 and Zonomi use it for supplementary settings.
- A credential-page link falls back to the provider documentation when the
  source has no reliable account settings URL. It must not point to a machine
  API endpoint merely because that endpoint appears in an example.

## Source exceptions and explicit decisions

All file/line references below are relative to `dnsapi/` at the pinned commit.
For example, [the pinned provider source directory](https://github.com/acmesh-official/acme.sh/tree/807da6498377ee5e0cf43a78091f46f12dc59a89/dnsapi)
is the source of these facts, not the moving default branch or wiki alone.

| Driver | Decision / runtime requirement | Source reference |
| --- | --- | --- |
| `dns_1984hosting` | TOTP secret is conditional on account 2FA; executor needs `oathtool`. | `dns_1984hosting.sh:132–137` |
| `dns_acmedns` | Existing registration needs username, password and subdomain; a new registration pauses for CNAME delegation. | `dns_acmedns.sh:28–98` |
| `dns_aws` | Access-key credentials and executor IAM role are separate paths; temporary credentials can include a session token. Role metadata access is an executor prerequisite. | `dns_aws.sh:21–48,220–263,295–297` |
| `dns_azion` | Correct the provider display name; preserve the two credential fields. | `dns_azion.sh:3–9,147–162` |
| `dns_azure` | Service principal, managed identity and supplied bearer token are distinct paths. Subscription is required in every path. The supplied bearer token is deliberately not persisted. | `dns_azure.sh:30–114` |
| `dns_baidu` | Extra hosts, engine, version and TXT-record settings supplement the same access-key pair; they are not a second authentication path. | `dns_baidu.sh:46–62,123–202,268–270` |
| `dns_beget` | Replace metadata's uppercase names with actual case-sensitive `Beget_Username` and `Beget_Password`. | `dns_beget.sh:7–8,25–28` |
| `dns_cf` | Token or legacy key/email; account and zone IDs are optional discovery filters, not mandatory token credentials. | `dns_cf.sh:24–70` |
| `dns_dnsimple` | Account ID can be discovered for one account; explicitly required when the token exposes multiple accounts. | `dns_dnsimple.sh:132–168` |
| `dns_cloudns` | Require regular auth ID **or** sub-auth ID, with password; do not require both IDs. | `dns_cloudns.sh:112–145` |
| `dns_creoline` | Both API token and API secret are required even though the header lacks detailed descriptions. | `dns_creoline.sh:22–30` |
| `dns_cyon` | Shared 2FA secret is conditional; `oathtool` and non-ASCII conversion via `idn` are executor prerequisites. | `dns_cyon.sh:63–64,83–92,174–182` |
| `dns_czechia` | Parse JSON `_info` instead of treating it as missing Options. Authorization token and zone list are required; API base has an actual fallback. | `dns_czechia.sh:3–13,167–176` |
| `dns_da` | TLS verification flag must be supplied; form supplies `0` explicitly. | `dns_da.sh:34–47` |
| `dns_dynv6` | REST token and registered SSH key-file paths are alternatives. SSH needs tooling, a registered key and host-key verification; absent key invokes interactive setup. | `dns_dynv6.sh:20–37,76–89,127–145` |
| `dns_efficientip` | Basic credential string or token-key/token-secret pair, together with server. DNS name and view are optional. | `dns_efficientip.sh:23–45` |
| `dns_gandi_livedns` | Add actual personal-access-token field omitted from metadata; token and legacy API key are alternatives. | `dns_gandi_livedns.sh:26–38` |
| `dns_gcloud` | Requires preauthenticated `gcloud`; active configuration name is optional. Environment is consumed by external CLI, not directly by this shell driver. | `dns_gcloud.sh:6–7,64–76,144–151` |
| `dns_googledomains` | Zone override is optional; automatic root/zone discovery is supported. | `dns_googledomains.sh:95–120` |
| `dns_hetznercloud` | Preserve the three fields under `Optional:` and actual defaults: TTL 120, cloud API URL and 120 polling attempts. | `dns_hetznercloud.sh:8–11,165–191` |
| `dns_inwx` | Shared secret is only needed for an account with 2FA; `oathtool` must be available. | `dns_inwx.sh:235–243` |
| `dns_ispconfig` | Form must explicitly supply TLS verification flag `0`; the driver rejects an absent value. | `dns_ispconfig.sh:39–57` |
| `dns_jd` | Region is optional with actual `cn-north-1` default. | `dns_jd.sh:16–17,33–47` |
| `dns_kas` | Despite metadata's advertised default, source rejects missing auth type. Form supplies `plain` as required default. | `dns_kas.sh:147–155` |
| `dns_knot` | Requires `knsupdate`; key field contains TSIG data, not a filename. | `dns_knot.sh:8,21–43` |
| `dns_la` | `LA_Token` is computed, not supplied. Pinned source logs both credential values unconditionally; executor must suppress/sanitize this sink before use. | `dns_la.sh:24–27,205–206` |
| `dns_level27` | Alternative section supplies optional endpoint, not alternative authentication. | `dns_level27.sh:6–10,98–116` |
| `dns_lexicon` | Generic adapter needs Lexicon and selected plugin's dynamic credential environment; one PROVIDER selector is not sufficient to make it portable. | `dns_lexicon.sh:15–85` |
| `dns_loopia` | Metadata's literal default `se` is not an API URL. Actual default is `https://api.loopia.se/RPCSERV`. | `dns_loopia.sh:7,12,92–99` |
| `dns_maradns` | Requires writable zone file, PID file and permission to signal the daemon; not a pure remote API provider. | `dns_maradns.sh:17–48` |
| `dns_myapi` | Deduplicate sample variable. This is a development skeleton; a real implementation must be supplied and reviewed before use. | `dns_myapi.sh:3–30` |
| `dns_mydevil` | Zero portable credential fields is valid: the authenticated hosting account and local `devil` CLI perform updates. Not portable to an ordinary VPS. | `dns_mydevil.sh:3–8,22–39` |
| `dns_netcup` | REST key, legacy CCP and REST-with-legacy-fallback have separate credential sets. Driver selects REST for a 64-character key. | `dns_netcup.sh:14–18,228–250,453–477` |
| `dns_nsd` | Requires a writable zone file and operator reload command; command execution requires separate executor admission. | `dns_nsd.sh:22–44,56–65` |
| `dns_nsupdate` | Requires `nsupdate`, permitted DNS update method and optional admitted key file. Empty key does not imply unauthenticated updates are permitted. | `dns_nsupdate.sh:19–34,48–75` |
| `dns_oci` | Inline key, key file or existing config profile are alternatives. Driver itself signs requests; no OCI CLI requirement. Key/config paths need executor admission. | `dns_oci.sh:96–100,139–188,337–345` |
| `dns_openstack` | Password and application-credential variants; project name or ID paths. OpenStackClient/Designate and deployment-specific Keystone domain rules remain prerequisites. | `dns_openstack.sh:3–6,180–205,327–369` |
| `dns_opnsense` | Optional TLS verification flag actually defaults to 0; preserve validation of allowed flag values. | `dns_opnsense.sh:247–253` |
| `dns_optidata` | Location is optional when default/discovery succeeds; help explains when manual code/UUID is needed rather than blindly requiring it. | `dns_optidata.sh:124–125,154–182,211–280` |
| `dns_ovh` | Unattended operation requires an already validated consumer key. Missing consumer key creates an authorization request, not a completed automatic login. | `dns_ovh.sh:120–162` |
| `dns_poweradmin` | API version is optional with actual fallback 2. | `dns_poweradmin.sh:29–30` |
| `dns_rcode0` | Service URL is optional with full actual endpoint default. | `dns_rcode0.sh:19,31–35` |
| `dns_regru` | Account login plus special API password; guide links to the provider's API settings and explains outbound-IP allowlisting. | `dns_regru.sh:5–9,20–29`; linked REG.RU API settings |
| `dns_selectel` | Current v2 service-user credentials and deprecated v1 key differ. Form supplies chosen version explicitly: source's absent-version fallback is v1. | `dns_selectel.sh:390–467` |
| `dns_selfhost` | Mapping of complete challenge names to existing record IDs is required; driver updates those existing records. | `dns_selfhost.sh:30–64` |
| `dns_technitium` | Actual environment variable is misspelled `Technitium_Expirty_Ttl`, unlike metadata. Explicit 0 selects cleanup without timer expiry. | `dns_technitium.sh:8–10,19–22,35–38` |
| `dns_transip` | Executor-readable signing key file and enabled API are prerequisites; a path field must not grant arbitrary host access. | `dns_transip.sh:141–166` |
| `dns_volcengine` | Session token is required only for temporary STS credentials, not permanent access keys. | `dns_volcengine.sh:214–216` |
| `dns_yandex360` | Existing access token or interactive device OAuth; organization can be discovered. Token-only mode does not guarantee later unattended renewal. | `dns_yandex360.sh:106–163,181–260,309–342` |
| `dns_yc` | Zone or folder lookup; inline Base64 PEM or file key. Four variants, all with service-account/key IDs. Inline key is materialized in working directory; isolate it. | `dns_yc.sh:25–79` |
| `dns_zonomi` | Extra API endpoint supplements the same API key; not a separate authentication method. | `dns_zonomi.sh:6–10,81–88` |

The non-overridden providers retain the explicit fields in their pinned metadata,
after the all-provider source branch review. The catalogue records per-driver
SHA-256, metadata SHA-256, source URL and `accountTested: false`; it must not label
metadata-backed coverage as successful account validation.

## Verification and remaining runtime work

The deterministic catalogue tests compare declared field inventory independently
of the production parser, allowing only documented `replaceFields` exceptions.
They verify unique field/variant names, bilingual key parity, absence of unresolved
conditional placeholders, exact pinned hashes and byte-for-byte regeneration.
The generated inventory at this review is **198 providers, 463 fields and 218
variants**. This is form coverage, not 198 issuance results.

Before execution, the managed Silesco path still needs an approved provider/runtime
capability, bounded secret injection, protected isolated files, controlled logging
and cleanup. The catalogue never grants arbitrary CLI flags, reload commands,
file paths or a bypass of Guard/helpers. Providers that depend on a hosted CLI,
dynamic Lexicon credentials or daemon access have explicit prerequisites and may
require a specialized executor; a rendered form is not permission to run them.

Real verification starts with the owner's REG.RU account on **Let's Encrypt
Staging**, covering TXT creation, propagation, issuance, cleanup, retry and secret
redaction. No production CA requests or provider-account mutations were made by
this catalogue review.
