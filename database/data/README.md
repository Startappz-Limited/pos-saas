# Data files

- `common-passwords.txt`: passwords the sign-up, invite, reset and password-change rules refuse
  (`App\Rules\StrongPassword`), one per line, lowercase. The project's own list merged with
  `Passwords/Common-Credentials/10k-most-common.txt` from [SecLists](https://github.com/danielmiessler/SecLists)
  (MIT licence), downloaded 24 October 2026.
- `disposable-domains.txt`: throwaway email domains refused at sign-up.
