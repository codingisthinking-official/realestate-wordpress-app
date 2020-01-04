<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('WP_CACHE', true);
define( 'WPCACHEHOME', '/var/www/html/wp-content/plugins/wp-super-cache/' );
define( 'DB_NAME', 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'wordpress' );

/** MySQL database password */
define( 'DB_PASSWORD', 'cdeb6423daa97f03431019782cb296d7270db6362bf8f3d4' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'YQ0-lKjY9]@>ma%!N!QXmY*d4Wz,EluA7F8#^j)fD@GIS_gTU&6*~!LZLP%710%:' );
define( 'SECURE_AUTH_KEY',  'ajy]bjDO1EewH|+!<9JG|Q~iNsl_+QzwQzqlq+^PFZxO>fzl:oAp/32mPEEJGk;0' );
define( 'LOGGED_IN_KEY',    'Milh>Du$d1Y1:ti-%a3OSibs:!)_F:YtT`r7&%RBh>j5w!C| ]DrteITO&z40;A?' );
define( 'NONCE_KEY',        'b;U$l{I4>cydW1]jAzgHgc Idabd9!d([{T}QVr@t[~(igA[+Iwz43R(hUB&2l*e' );
define( 'AUTH_SALT',        'ZNx8%B*orng]U-uH0+kdpI#Z%}}|-H_<e;-0j7Sf@cGb!`t~.ZtPY10^8gPA~WaZ' );
define( 'SECURE_AUTH_SALT', 'b]iI6SkJ[eJ1lTQ%Kr:x@cjpodQa:njn{6E{8&_sl_/9-ym{b]6!w*4e<FeY[$g#' );
define( 'LOGGED_IN_SALT',   'AOCc`KRk%W-TD1,lmxhrR3~{|z6gd1AO-Lg YD8v&lV&pGpcjjQ=&|]ax[F[O-)^' );
define( 'NONCE_SALT',       '8`YHJ4dkR9>K9h|F|g^DZj#:m=jf}$TFso_M;uh?*&Xu[X9^%Pp7 pj}hK%~,Qm<' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once( ABSPATH . 'wp-settings.php' );
