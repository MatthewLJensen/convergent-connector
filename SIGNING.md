# Module signing

FreePBX warns about modules that aren't signed by a key it trusts. This module
is signed with the Convergent release key, the same key as OpenProv. A system
trusts it after running one command as root:

```sh
fwconsole convergent trust-key            # import the key and trust it
fwconsole convergent trust-key --status   # is it trusted?
fwconsole convergent trust-key --remove   # stop trusting it
```

`fwconsole openprov trust-key` does the same thing on systems that have OpenProv.
Trusting the key once covers both modules.

Release key fingerprint:

```
E40F A5E8 A108 D1B8 2640  8E0D B63E 9EEE E8CD A76F
Matthew Jensen (FreePBX module releases) <matthewljensen@gmail.com>
```

## What trust-key changes

FreePBX verifies `module.sig` with `gpg` as the web user (asterisk), using that
user's keyring. `trust-key` does three things:

- **Imports the key.** The public key comes from `keys/release-signing-key.asc` in this module, not from a keyserver. It is accepted only if its fingerprint matches the one pinned in `Signing.php`.
- **Marks it ultimately trusted** in that keyring. FreePBX does the same for its own key.
- **Re-checks module signatures** so the warning updates right away.

Trust is system-wide: any module signed with this key passes. The key is used
only to sign module releases. Sangoma's master key isn't involved. FreePBX only
needs to trust the signer.

## Releasing (maintainers)

```sh
git config core.hooksPath githooks   # once per clone (not hooks/: that's FreePBX's)
bin/module-sign sign                 # sign the committed tree, commit module.sig
bin/module-sign release              # sign, tag v<version>, build dist/convergentdashboard-<version>.tgz
```

`sign` asks for the release key's passphrase every time. It signs what is
committed, which is exactly what a git clone or a GitHub release zipball (the
"Check for Updates" button) installs. The `pre-push` hook refuses to push an
unsigned or stale `module.sig` to `main` or a `v*` tag.

`bin/module-sign`, `githooks/pre-push` and `Signing.php` are copies of
OpenProv's (`bin/`, `githooks/`, `src/Signing.php`); keep them in step. OpenProv's
`docs/SIGNING.md` explains the FreePBX side in more detail, including why the
signing subkey has no expiry.
