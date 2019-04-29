# Pantheon Domain Masking

This module allows domain masking in Drupal 8 for environments where Drupal is not running under Apache, or where the hosting configuration is unavailable.

Typically domain masking can be facilitated by adding a few lines to a `.htaccess` or `nginx.conf` file; however, if that method is unavailable, this module allows Drupal to be aware of changes to the host and to persist those changes when generating redirects.

## Enabling the module

Once the module has been installed to the filesystem, it can be enabled like any other contrib module. However, this will not enable the domain masking functionality. Once the module is active, the config page for the module (`/admin/config/pantheon-domain-masking/options`) will allow you to enter the public-facing domain name. You will need to toggle the `Enable domain masking?` field on this page to enable the middleware.

### Example

Assume a site is running on Pantheon with a live environment address of `https://live-example.pantheonsite.io`. Assume the public-facing domain you wish to use is `https://www.example.com`. In this case, you would enter `www.example.com` in the `Public-facing domain:` field. Once the `Enable domain masking?` field is set to `Yes`, Drupal will use `www.example.com` in generating any internal redirects.

## Allowing platform access

Under normal circumstances, this module will force all requests to use the masked domain. However, if you wish to access this site via Pantheon's platform domain (ending in `.pantheonsite.io`) without going through the masked domain, set the `Allow Platform domain access?` field to `Yes`.

### Example

Following the example above, suppose the public-facing domain was masking two different Pantheon environments, eg. `https://live-example-a.pantheonsite.io` and `https://live-example-b.pantheonsite.io`. Navigating to `https://www.example.com/user/login` would resolve to whatever backend the edge server chose for that request and, depending on the configuration (eg. round-robin) may not be consistent from request to request. In order to manage both the `A` and `B` sites directly, the `Allow Platform domain access?` field would need to be set to `Yes` on both sites, and content managers would need to access `https://live-example-a.pantheonsite.io/user/login` or `https://live-example-b.pantheonsite.io/user/login` directly to manage those specific instances.