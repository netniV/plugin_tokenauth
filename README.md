## Plugin Token Authentication
This plugin is designed to allow you to setup a user with a public token to verify credentials without knowing the users password.

This is a useful feature when you are attempt to link graphs for viewing from other systems and even enables you to utilise guest accounts to provide limited access to specific graphs.

Currently, only RSA public key verification is utilised, though in the future, this plugin will be expanded to allow other similar methods.

## Installation

To install the plugin, simply copy the plugin_tokenauth directory to Cacti's plugins directory and rename it to simply 'tokenauth'. Once this is complete, goto Cacti's Plugin Management section, and Install and Enable the plugin. Once this is complete, you can grant users permission to use the plugin (admin is granted permissions by default).

After you have completed that, you should goto 'Token Auth' on the Console Menu under 'Utilities'.  Click 'Add' to add a new Token Authentication to test.

## Third Paty Sites

In order to utilise the token authentication, you will need to supply a userid and a token as part of the url:

`https://<cacti>/graph_view.php?tokenauth_userid=<userid>&tokenauth_token=<token>`

The token should be the signed token generated using the private RSA token that corresponds to the public token setup within Cacti.

The text that should be signed is made up of the following:
```
<date><salt><userid>
```
field | example | description
--- | --- | ---
date | 20180818 | This is the reverse date in the format of 4 digit year, 2 digit month, 2 digit day
salt | mysaltfield | This is the salt that was setup with against the Token Authentication in Cacti
userid | 325 | This is the user id of the user within cacti

Note, both the user and the Token Authentication **must** be enabled in order to for the authentication to pass.

## Generating Keys
When editing the Token Authentication, there is an option to generate keys in 1024-bit, 2048-bit and 4096-bit formats.  When using these functions to generate a new key, rather than using your own pre-existing one, you MUST make sure that you copy the private key for later usage.

Without the private key, you will NOT be able to sign and verify the above text.

## Example PHP script
The following is an example of a PHP script that will generate a signed token to be passd to cacti, using the Token Auth authentication system variables

This example assumes that you have the following:
- phpseclib copied into a subfolder
- loaded the private key into $key
- loaded the public key into Token of the tokenauth record
- loaded the salt into $salt and Salt of the tokenauth record
- primed the TokenAuth ID in $id

```php
include_once('phpseclib/Math/BigInteger.php');
include_once('phpseclib/Crypt/Random.php');
include_once('phpseclib/Crypt/Hash.php');
include_once('phpseclib/Crypt/RSA.php');
$rsa = new \phpseclib\Crypt\RSA();
$rsa->setHash('sha256');
$rsa->loadKey($key);

$package = date('Ymd') . $salt . $id;
$original = $rsa->sign($package);
$base64 = base64_encode($original);
$signature = urlencode($base64);
echo "$package = " . $base64. "\n";
echo "http://cacti/?tokenauth_id=$id&tokenauth_token=$signature";
```

## Releases

--- 0.0.1 ---
Initial version
