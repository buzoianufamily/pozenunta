PHP Version 8.5.8
System	Linux server-0427.whmpanels.com 5.14.0-611.49.1.el9_7.x86_64 #1 SMP PREEMPT_DYNAMIC Tue Apr 21 16:39:08 EDT 2026 x86_64
Build Date	Jul 9 2026 00:00:00
Build System	Linux buildfarm07-new.corp.cloudlinux.com 5.14.0-687.15.1.el9_8.x86_64 #1 SMP PREEMPT_DYNAMIC Thu Jun 11 08:51:45 EDT 2026 x86_64 x86_64 x86_64 GNU/Linux
Configure Command	'./configure' '--disable-dependency-tracking' '--prefix=/usr' '--exec-prefix=/usr' '--bindir=/usr/bin' '--sbindir=/usr/sbin' '--sysconfdir=/etc' '--datadir=/usr/share' '--includedir=/usr/include' '--libdir=/usr/lib64' '--libexecdir=/usr/libexec' '--sharedstatedir=/var/lib' '--mandir=/usr/share/man' '--infodir=/usr/share/info' '--build=x86_64-redhat-linux-gnu' '--host=x86_64-redhat-linux-gnu' '--target=x86_64-redhat-linux-gnu' '--program-prefix=' '--prefix=/opt/alt/php85' '--exec-prefix=/opt/alt/php85' '--bindir=/opt/alt/php85/usr/bin' '--sbindir=/opt/alt/php85/usr/sbin' '--sysconfdir=/opt/alt/php85/etc' '--datadir=/opt/alt/php85/usr/share' '--includedir=/opt/alt/php85/usr/include' '--libdir=/opt/alt/php85/usr/lib64' '--libexecdir=/opt/alt/php85/usr/libexec' '--localstatedir=/var' '--sharedstatedir=/usr/com' '--mandir=/opt/alt/php85/usr/share/man' '--infodir=/opt/alt/php85/usr/share/info' '--cache-file=../config.cache' '--with-libdir=lib64' '--with-config-file-path=/opt/alt/php85/etc' '--with-config-file-scan-dir=/opt/alt/php85/link/conf' '--disable-debug' '--enable-calendar' '--enable-exif' '--enable-ftp' '--enable-huge-code-pages' '--enable-jit' '--enable-shared=yes' '--enable-shmop' '--enable-static=no' '--enable-xml' '--with-bz2' '--with-freetype=/usr' '--with-gettext' '--with-gmp=shared' '--with-gnu-ld=yes' '--with-iconv' '--with-jpeg=/usr' '--with-layout=GNU' '--with-mhash' '--with-password-argon2=/usr' '--with-pcre-jit' '--with-pic' '--with-readline' '--with-webp=/opt/alt/libwebp' '--with-xpm=/usr' '--with-zlib' '--with-zlib-dir=/usr' '--without-gdbm' '--without-pear' '--with-curl=/opt/alt/curlssl30/usr' '--with-openssl-dir=/usr' '--with-openssl=/usr' '--with-pcre-dir=/usr' '--with-avif' '--with-kerberos' '--with-apxs2' '--enable-fpm' '--with-fpm-systemd' '--enable-litespeed' '--enable-bcmath=shared' '--enable-dba=shared' '--with-db4=/usr' '--enable-dom=shared' '--enable-fileinfo=shared' '--enable-gd=shared' '--enable-intl=shared' '--enable-mbregex' '--enable-mbstring=shared' '--enable-mysqlnd=shared' '--enable-pcntl' '--enable-pdo=shared' '--enable-phar=shared' '--enable-posix=shared' '--enable-soap=shared' '--enable-sockets=shared' '--enable-sysvsem=shared' '--enable-sysvshm=shared' '--enable-sysvmsg=shared' '--enable-xmlreader=shared' '--enable-xmlwriter=shared' '--with-enchant=shared,/usr' '--with-external-gd' '--with-mysql-sock=/var/lib/mysql/mysql.sock' '--with-mysqli=shared,mysqlnd' '--with-pdo-mysql=shared,mysqlnd' '--with-pdo-odbc=shared,unixODBC,/usr' '--with-pdo-sqlite=shared,/opt/alt/sqlite/usr' '--with-sqlite3=/opt/alt/sqlite/usr' '--with-tidy=shared,/usr' '--with-unixODBC=shared,/usr' '--with-zip=shared' '--with-pdo-pgsql=shared,/usr' '--with-pgsql=shared,/usr' '--with-pdo-dblib=shared,/usr' '--with-ldap=shared,/usr' '--with-ldap-sasl' '--with-snmp=shared,/usr' '--with-pdo-oci=shared,instantclient,/usr/lib/oracle/21/client64/lib' '--with-sodium=shared,/usr' '--with-ffi=shared,/usr' '--with-pdo-firebird=shared' '--with-xsl=shared,/usr' 'build_alias=x86_64-redhat-linux-gnu' 'host_alias=x86_64-redhat-linux-gnu' 'target_alias=x86_64-redhat-linux-gnu' 'PKG_CONFIG_PATH=/opt/alt/curlssl30/usr/lib64/pkgconfig:/opt/alt/libssh211/usr/lib64/pkgconfig:/opt/alt/sqlite/usr/lib64/pkgconfig:/opt/alt/libxml2/usr/lib64/pkgconfig:/opt/alt/libicu71/usr/lib64/pkgconfig:/opt/alt/libgd/lib64/pkgconfig::/usr/lib64/pkgconfig:/usr/share/pkgconfig' 'CFLAGS=-O2 -fexceptions -g -grecord-gcc-switches -pipe -Wall -Werror=format-security -Wp,-D_FORTIFY_SOURCE=2 -Wp,-D_GLIBCXX_ASSERTIONS -specs=/usr/lib/rpm/redhat/redhat-hardened-cc1 -fstack-protector-strong -specs=/usr/lib/rpm/redhat/redhat-annobin-cc1 -m64 -march=x86-64-v2 -mtune=generic -fasynchronous-unwind-tables -fstack-clash-protection -fcf-protection -Dmp_bitcnt_t=long -fno-strict-aliasing -Wno-pointer-sign -I/opt/alt/libxml2/usr/include/libxml2 -I/opt/alt/libicu71/usr/include -I/opt/alt/libgd/include -I/usr/include/libpng16 -I/usr/include/freetype2 -I/usr/include/harfbuzz -I/usr/include/glib-2.0 -I/usr/lib64/glib-2.0/include -I/usr/include/sysprof-4 -pthread -DAVIF_DLL -mshstk -fcf-protection' 'CXXFLAGS=-O2 -fexceptions -g -grecord-gcc-switches -pipe -Wall -Werror=format-security -Wp,-D_FORTIFY_SOURCE=2 -Wp,-D_GLIBCXX_ASSERTIONS -specs=/usr/lib/rpm/redhat/redhat-hardened-cc1 -fstack-protector-strong -specs=/usr/lib/rpm/redhat/redhat-annobin-cc1 -m64 -march=x86-64-v2 -mtune=generic -fasynchronous-unwind-tables -fstack-clash-protection -fcf-protection' 'WEBP_LIBS=-L/opt/alt/libwebp/lib64 -Wl,-rpath=/opt/alt/libwebp/lib64' 'SASL_CFLAGS=-I/usr/include' 'SASL_LIBS=-L/usr/lib64' 'EXTENSION_DIR=/opt/alt/php85/usr/lib64/php/modules' 'LT_SYS_LIBRARY_PATH=/usr/lib64:'
Server API	lsapi V8.3 CloudLinux 1.3
Virtual Directory Support	disabled
Configuration File (php.ini) Path	/opt/alt/php85/etc
Loaded Configuration File	/opt/alt/php85/etc/php.ini
Scan this dir for additional .ini files	/opt/alt/php85/link/conf
Additional .ini files parsed	/opt/alt/php85/link/conf/alt_php.ini
PHP API	20250925
PHP Extension	20250925
Zend Extension	420250925
Zend Extension Build	API420250925,NTS
PHP Extension Build	API20250925,NTS
PHP Integer Size	64 bits
Debug Build	no
Thread Safety	disabled
Zend Signal Handling	enabled
Zend Memory Manager	enabled
Zend Multibyte Support	provided by mbstring
Zend Max Execution Timers	disabled
IPv6 Support	enabled
DTrace Support	disabled
Registered PHP Streams	https, ftps, compress.zlib, compress.bzip2, php, file, glob, data, http, ftp, phar, zip
Registered Stream Socket Transports	tcp, udp, unix, udg, ssl, tls, tlsv1.0, tlsv1.1, tlsv1.2, tlsv1.3
Registered Stream Filters	zlib.*, bzip2.*, convert.iconv.*, string.rot13, string.toupper, string.tolower, convert.*, consumed, dechunk, mcrypt.*, mdecrypt.*
Zend logoThis program makes use of the Zend Scripting Language Engine:
Zend Engine v4.5.8, Copyright (c) Zend Technologies with Zend OPcache v8.5.8, Copyright (c), by Zend Technologies
Configuration
apcu
APCu Support	Enabled
Version	5.1.28
APCu Debugging	Disabled
MMAP Support	Enabled
MMAP File Mask	no value
Serialization Support	php
Build Date	Jul 23 2026 00:00:00
Directive	Local Value	Master Value
apc.coredump_unmap	Off	Off
apc.enable_cli	Off	Off
apc.enabled	On	On
apc.entries_hint	0	0
apc.gc_ttl	3600	3600
apc.mmap_file_mask	no value	no value
apc.mmap_hugepage_size	0	0
apc.preload_path	no value	no value
apc.serializer	php	php
apc.shm_size	32M	32M
apc.slam_defense	Off	Off
apc.smart	0	0
apc.ttl	0	0
apc.use_request_time	Off	Off
bcmath
BCMath support	enabled
Directive	Local Value	Master Value
bcmath.scale	0	0
bz2
BZip2 Support	Enabled
Stream Wrapper support	compress.bzip2://
Stream Filter support	bzip2.decompress, bzip2.compress
BZip2 Version	1.0.8, 13-Jul-2019
calendar
Calendar support	enabled
Core
PHP Version	8.5.8
Directive	Local Value	Master Value
allow_url_fopen	On	On
allow_url_include	Off	Off
arg_separator.input	&	&
arg_separator.output	&	&
auto_append_file	no value	no value
auto_globals_jit	Off	Off
auto_prepend_file	no value	no value
browscap	no value	no value
default_charset	UTF-8	UTF-8
default_mimetype	text/html	text/html
disable_functions	show_source, system, passthru, popen	show_source, system, passthru, popen
display_errors	On	On
display_startup_errors	Off	Off
doc_root	no value	no value
docref_ext	no value	no value
docref_root	no value	no value
enable_dl	Off	Off
enable_post_data_reading	On	On
error_append_string	no value	no value
error_log	error_log	error_log
error_log_mode	0644	0644
error_prepend_string	no value	no value
error_reporting	22519	22519
expose_php	Off	Off
extension_dir	/opt/alt/php85/usr/lib64/php/modules	/opt/alt/php85/usr/lib64/php/modules
fatal_error_backtraces	On	On
fiber.stack_size	no value	no value
file_uploads	On	On
hard_timeout	2	2
highlight.comment	#FF8000	#FF8000
highlight.default	#0000BB	#0000BB
highlight.html	#000000	#000000
highlight.keyword	#007700	#007700
highlight.string	#DD0000	#DD0000
html_errors	On	On
ignore_repeated_errors	On	On
ignore_repeated_source	On	On
ignore_user_abort	Off	Off
implicit_flush	Off	Off
include_path	.:/opt/alt/php85/usr/share/pear:/opt/alt/php85/usr/share/php:/usr/share/pear:/usr/share/php	.:/opt/alt/php85/usr/share/pear:/opt/alt/php85/usr/share/php:/usr/share/pear:/usr/share/php
input_encoding	no value	no value
internal_encoding	no value	no value
log_errors	On	On
mail.add_x_header	Off	Off
mail.cr_lf_mode	crlf	crlf
mail.force_extra_parameters	no value	no value
mail.log	no value	no value
mail.mixed_lf_and_crlf	Off	Off
max_execution_time	300	300
max_file_uploads	20	20
max_input_nesting_level	64	64
max_input_time	300	300
max_input_vars	10000	10000
max_memory_limit	-1	-1
max_multipart_body_parts	-1	-1
memory_limit	2048M	2048M
open_basedir	no value	no value
output_buffering	4096	4096
output_encoding	no value	no value
output_handler	no value	no value
post_max_size	1024M	1024M
precision	14	14
realpath_cache_size	2048K	2048K
realpath_cache_ttl	3600	3600
register_argc_argv	Off	Off
report_memleaks	On	On
report_zend_debug	Off	Off
request_order	GP	GP
sendmail_from	no value	no value
sendmail_path	/usr/sbin/sendmail -t -i	/usr/sbin/sendmail -t -i
serialize_precision	-1	-1
short_open_tag	Off	Off
SMTP	localhost	localhost
smtp_port	25	25
sys_temp_dir	no value	no value
syslog.facility	LOG_USER	LOG_USER
syslog.filter	no-ctrl	no-ctrl
syslog.ident	php	php
unserialize_callback_func	no value	no value
upload_max_filesize	1024M	1024M
upload_tmp_dir	no value	no value
user_dir	no value	no value
user_ini.cache_ttl	300	300
user_ini.filename	.user.ini	.user.ini
variables_order	GPCS	GPCS
xmlrpc_error_number	0	0
xmlrpc_errors	Off	Off
zend.assertions	-1	-1
zend.detect_unicode	On	On
zend.enable_gc	On	On
zend.exception_ignore_args	On	On
zend.exception_string_param_max_len	15	15
zend.max_allowed_stack_size	0	0
zend.multibyte	Off	Off
zend.reserved_stack_size	0	0
zend.script_encoding	no value	no value
zend.signal_check	Off	Off
ctype
ctype functions	enabled
curl
cURL support	enabled
cURL Information	8.21.0
Age	11
Features
AsynchDNS	Yes
CharConv	No
Debug	No
GSS-Negotiate	No
IDN	Yes
IPv6	Yes
krb4	No
Largefile	Yes
libz	Yes
NTLM	No
NTLMWB	No
SPNEGO	Yes
SSL	Yes
SSPI	No
TLS-SRP	Yes
HTTP2	Yes
GSSAPI	Yes
KERBEROS5	Yes
UNIX_SOCKETS	Yes
PSL	Yes
HTTPS_PROXY	Yes
MULTI_SSL	No
BROTLI	No
ALTSVC	Yes
HTTP3	No
UNICODE	No
ZSTD	No
HSTS	Yes
GSASL	No
Protocols	dict, file, ftp, ftps, gopher, gophers, http, https, imap, imaps, ldap, ldaps, mqtt, mqtts, pop3, pop3s, rtsp, scp, sftp, smtp, smtps, telnet, tftp, ws, wss
Host	x86_64-redhat-linux-gnu
SSL Version	OpenSSL/3.5.5
ZLib Version	1.2.11
libSSH Version	libssh2/1.11.1
Directive	Local Value	Master Value
curl.cainfo	no value	no value
date
date/time support	enabled
timelib version	2022.15
"Olson" Timezone Database Version	2026.3
Timezone Database	external
Default timezone	Europe/Bucharest
Directive	Local Value	Master Value
date.default_latitude	31.7667	31.7667
date.default_longitude	35.2333	35.2333
date.sunrise_zenith	90.833333	90.833333
date.sunset_zenith	90.833333	90.833333
date.timezone	Europe/Bucharest	Europe/Bucharest
dom
DOM/XML	enabled
DOM/XML API Version	20031129
libxml Version	2.10.2
HTML Support	enabled
XPath Support	enabled
XPointer Support	enabled
Schema Support	enabled
RelaxNG Support	enabled
exif
EXIF Support	enabled
Supported EXIF Version	0220
Supported filetypes	JPEG, TIFF
Multibyte decoding support using mbstring	enabled
Extended EXIF tag formats	Canon, Casio, Fujifilm, Nikon, Olympus, Samsung, Panasonic, DJI, Sony, Pentax, Minolta, Sigma, Foveon, Kyocera, Ricoh, AGFA, Epson
Directive	Local Value	Master Value
exif.decode_jis_intel	JIS	JIS
exif.decode_jis_motorola	JIS	JIS
exif.decode_unicode_intel	UCS-2LE	UCS-2LE
exif.decode_unicode_motorola	UCS-2BE	UCS-2BE
exif.encode_jis	no value	no value
exif.encode_unicode	ISO-8859-15	ISO-8859-15
fileinfo
fileinfo support	enabled
libmagic	5.46
filter
Input Validation and Filtering	enabled
Directive	Local Value	Master Value
filter.default	unsafe_raw	unsafe_raw
filter.default_flags	no value	no value
ftp
FTP support	enabled
FTPS support	enabled
gd
GD Support	enabled
GD headers Version	2.3.3
GD library Version	2.3.3
FreeType Support	enabled
FreeType Linkage	with freetype
GIF Read Support	enabled
GIF Create Support	enabled
JPEG Support	enabled
PNG Support	enabled
WBMP Support	enabled
XPM Support	enabled
XBM Support	enabled
WebP Support	enabled
BMP Support	enabled
AVIF Support	enabled
TGA Read Support	enabled
Directive	Local Value	Master Value
gd.jpeg_ignore_warning	On	On
geoip
geoip support	enabled
geoip extension version	1.1.1
geoip library version	1006012
Directive	Local Value	Master Value
geoip.custom_directory	no value	no value
gettext
GetText Support	enabled
hash
hash support	enabled
Hashing Engines	md2 md4 md5 sha1 sha224 sha256 sha384 sha512/224 sha512/256 sha512 sha3-224 sha3-256 sha3-384 sha3-512 ripemd128 ripemd160 ripemd256 ripemd320 whirlpool tiger128,3 tiger160,3 tiger192,3 tiger128,4 tiger160,4 tiger192,4 snefru snefru256 gost gost-crypto adler32 crc32 crc32b crc32c fnv132 fnv1a32 fnv164 fnv1a64 joaat murmur3a murmur3c murmur3f xxh32 xxh64 xxh3 xxh128 haval128,3 haval160,3 haval192,3 haval224,3 haval256,3 haval128,4 haval160,4 haval192,4 haval224,4 haval256,4 haval128,5 haval160,5 haval192,5 haval224,5 haval256,5
MHASH support	Enabled
MHASH API Version	Emulated Support
iconv
iconv support	enabled
iconv implementation	glibc
iconv library version	2.34
Directive	Local Value	Master Value
iconv.input_encoding	no value	no value
iconv.internal_encoding	no value	no value
iconv.output_encoding	no value	no value
igbinary
igbinary support	enabled
igbinary version	3.2.16
igbinary APCu serializer ABI	no
igbinary session support	yes
Directive	Local Value	Master Value
igbinary.compact_strings	On	On
imagick
imagick module	enabled
imagick module version	3.8.1
imagick classes	Imagick, ImagickDraw, ImagickPixel, ImagickPixelIterator, ImagickKernel
Imagick compiled with ImageMagick version	ImageMagick 7.1.2-29 Q16-HDRI x86_64 24471 https://imagemagick.org
Imagick using ImageMagick library version	ImageMagick 7.1.2-29 Q16-HDRI x86_64 24471 https://imagemagick.org
ImageMagick copyright	(C) 1999 ImageMagick Studio LLC
ImageMagick release date	2026-07-28
ImageMagick number of supported formats:	282
ImageMagick supported formats	3FR, 3G2, 3GP, A, AAI, AI, APNG, ART, ARW, ASHLAR, AVCI, AVI, AVIF, AVS, B, BAYER, BAYERA, BGR, BGRA, BGRO, BIE, BMP, BMP2, BMP3, BRF, C, CAL, CALS, CANVAS, CAPTION, CIN, CIP, CLIP, CMYK, CMYKA, CR2, CR3, CRW, CUBE, CUR, CUT, DATA, DCM, DCR, DCRAW, DCX, DDS, DFONT, DNG, DOT, DPX, DXT1, DXT5, EPDF, EPI, EPS, EPS2, EPS3, EPSF, EPSI, EPT, EPT2, EPT3, ERF, EXR, FARBFELD, FAX, FF, FFF, FILE, FITS, FL32, FLV, FRACTAL, FTP, FTS, FTXT, G, G3, G4, GIF, GIF87, GRADIENT, GRAY, GRAYA, GROUP4, GV, HALD, HDR, HEIC, HEIF, HISTOGRAM, HRZ, HTM, HTML, HTTP, HTTPS, ICB, ICN, ICO, ICON, IIQ, INFO, INLINE, IPL, ISOBRL, ISOBRL6, J2C, J2K, JBG, JBIG, JNG, JNX, JP2, JPC, JPE, JPEG, JPG, JPM, JPS, JPT, JSON, K, K25, KDC, LABEL, M, M2V, M4V, MAC, MAP, MASK, MAT, MATTE, MDC, MEF, MIFF, MKV, MNG, MONO, MOS, MOV, MP4, MPC, MPEG, MPG, MPO, MRW, MSL, MSVG, MTV, MVG, NEF, NRW, NULL, O, ORA, ORF, OTB, OTF, PAL, PALM, PAM, PANGO, PATTERN, PBM, PCD, PCDS, PCL, PCT, PCX, PDB, PDF, PDFA, PEF, PES, PFA, PFB, PFM, PGM, PGX, PHM, PICON, PICT, PIX, PJPEG, PLASMA, PNG, PNG00, PNG24, PNG32, PNG48, PNG64, PNG8, PNM, POCKETMOD, PPM, PS, PS2, PS3, PSB, PSD, PTIF, PWP, QOI, R, RADIAL-GRADIENT, RAF, RAS, RAW, RGB, RGB565, RGBA, RGBO, RGF, RLA, RLE, RMF, RSVG, RW2, RWL, SCR, SCREENSHOT, SCT, SF3, SFW, SGI, SHTML, SIX, SIXEL, SPARSE-COLOR, SR2, SRF, SRW, STEGANO, STI, STRIMG, SUN, SVG, SVGZ, TEXT, TGA, THUMBNAIL, TIFF, TIFF64, TILE, TIM, TM2, TTC, TTF, TXT, UBRL, UBRL6, UIL, UYVY, VDA, VICAR, VID, VIFF, VIPS, VST, WBMP, WEBM, WEBP, WMF, WMV, WMZ, WPG, X, X3F, XBM, XC, XCF, XPM, XPS, XV, XWD, Y, YAML, YCBCR, YCBCRA, YUV
Directive	Local Value	Master Value
imagick.allow_zero_dimension_images	0	0
imagick.locale_fix	0	0
imagick.progress_monitor	0	0
imagick.set_single_thread	1	1
imagick.shutdown_sleep_count	10	10
imagick.skip_version_check	0	0
imap
IMAP extension Version	1.0.3
IMAP c-Client Version	2007f
SSL Support	enabled
Kerberos Support	enabled
Directive	Local Value	Master Value
imap.enable_insecure_rsh	Off	Off
intl
Internationalization support	enabled
ICU version	71.1
ICU Data version	71.1
ICU TZData version	2026c
ICU Unicode version	14.0
Directive	Local Value	Master Value
intl.default_locale	no value	no value
intl.error_level	0	0
intl.use_exceptions	Off	Off
json
json support	enabled
lexbor
Lexbor support	active
Lexbor version	2.7.0
libxml
libXML support	active
libXML Compiled Version	2.10.2
libXML Loaded Version	21002
libXML streams	enabled
mbstring
Multibyte Support	enabled
Multibyte string engine	libmbfl
HTTP input encoding translation	disabled
libmbfl version	1.3.2
mbstring extension makes use of "streamable kanji code filter and converter", which is distributed under the GNU Lesser General Public License version 2.1.
Multibyte (japanese) regex support	enabled
Multibyte regex (oniguruma) version	6.9.6
Directive	Local Value	Master Value
mbstring.detect_order	no value	no value
mbstring.encoding_translation	Off	Off
mbstring.http_input	no value	no value
mbstring.http_output	no value	no value
mbstring.http_output_conv_mimetypes	^(text/|application/xhtml\+xml)	^(text/|application/xhtml\+xml)
mbstring.internal_encoding	no value	no value
mbstring.language	neutral	neutral
mbstring.regex_retry_limit	1000000	1000000
mbstring.regex_stack_limit	100000	100000
mbstring.strict_detection	Off	Off
mbstring.substitute_character	no value	no value
mcrypt
mcrypt support	enabled
mcrypt_filter support	enabled
Extension version	1.0.7
Library version	2.5.8
Api No	20021217
Supported ciphers	cast-128 gost rijndael-128 twofish arcfour cast-256 loki97 rijndael-192 saferplus wake blowfish-compat des rijndael-256 serpent xtea blowfish enigma rc2 tripledes
Supported modes	cbc cfb ctr ecb ncfb nofb ofb stream
Directive	Local Value	Master Value
mcrypt.algorithms_dir	no value	no value
mcrypt.modes_dir	no value	no value
msgpack
MessagePack Support	enabled
Session Support	enabled
MessagePack APCu Serializer ABI	no
extension Version	3.0.1
header Version	3.2.0
Directive	Local Value	Master Value
msgpack.assoc	On	On
msgpack.error_display	On	On
msgpack.force_f32	Off	Off
msgpack.illegal_key_insert	Off	Off
msgpack.php_only	On	On
msgpack.use_str8_serialization	On	On
mysqli
MysqlI Support	enabled
Client API library version	mysqlnd 8.5.8
Active Persistent Links	0
Inactive Persistent Links	0
Active Links	0
Directive	Local Value	Master Value
mysqli.allow_local_infile	Off	Off
mysqli.allow_persistent	On	On
mysqli.default_host	no value	no value
mysqli.default_port	3306	3306
mysqli.default_pw	no value	no value
mysqli.default_socket	/var/lib/mysql/mysql.sock	/var/lib/mysql/mysql.sock
mysqli.default_user	no value	no value
mysqli.local_infile_directory	no value	no value
mysqli.max_links	60	60
mysqli.max_persistent	Unlimited	Unlimited
mysqli.rollback_on_cached_plink	Off	Off
mysqlnd
mysqlnd	enabled
Version	mysqlnd 8.5.8
Compression	supported
core SSL	supported
extended SSL	supported
Command buffer size	4096
Read buffer size	32768
Read timeout	86400
Collecting statistics	Yes
Collecting memory statistics	No
Tracing	n/a
Loaded plugins	mysqlnd,debug_trace,auth_plugin_mysql_native_password,auth_plugin_mysql_clear_password,auth_plugin_caching_sha2_password,auth_plugin_sha256_password
API Extensions	mysqli,pdo_mysql
openssl
OpenSSL support	enabled
OpenSSL Library Version	OpenSSL 3.5.5 27 Jan 2026
OpenSSL Header Version	OpenSSL 3.5.5 27 Jan 2026
Openssl default config	/etc/pki/tls/openssl.cnf
Directive	Local Value	Master Value
openssl.cafile	no value	no value
openssl.capath	no value	no value
openssl.libctx	custom	custom
pcntl
pcntl support	enabled
pcre
PCRE (Perl Compatible Regular Expressions) Support	enabled
PCRE Library Version	10.44 2024-06-07
PCRE Unicode Version	15.0.0
PCRE JIT Support	enabled
PCRE JIT Target	x86 64bit (little endian + unaligned)
Directive	Local Value	Master Value
pcre.backtrack_limit	1000000	1000000
pcre.jit	On	On
pcre.recursion_limit	100000	100000
PDO
PDO support	enabled
PDO drivers	mysql, sqlite
pdo_mysql
PDO Driver for MySQL	enabled
Client API version	mysqlnd 8.5.8
Directive	Local Value	Master Value
pdo_mysql.default_socket	/var/lib/mysql/mysql.sock	/var/lib/mysql/mysql.sock
pdo_sqlite
PDO Driver for SQLite 3.x	enabled
SQLite Library	3.53.4
Phar
Phar: PHP Archive support	enabled
Phar API version	1.1.1
Phar-based phar archives	enabled
Tar-based phar archives	enabled
ZIP-based phar archives	enabled
gzip compression	enabled
bzip2 compression	enabled
OpenSSL support	enabled
Phar based on pear/PHP_Archive, original concept by Davey Shafik.
Phar fully realized by Gregory Beaver and Marcus Boerger.
Portions of tar implementation Copyright (c) 2003-2009 Tim Kientzle.
Directive	Local Value	Master Value
phar.cache_list	no value	no value
phar.readonly	On	On
phar.require_hash	On	On
posix
POSIX support	enabled
random
Version	8.5.8
readline
Readline Support	enabled
Readline library	8.1
Directive	Local Value	Master Value
cli.pager	no value	no value
cli.prompt	\b \>	\b \>
redis
Redis Support	enabled
Redis Version	6.3.0
Redis Sentinel Version	1.0
Available serializers	php, json, igbinary, msgpack
Available compression	zstd, lz4
Directive	Local Value	Master Value
redis.arrays.algorithm	no value	no value
redis.arrays.auth	no value	no value
redis.arrays.autorehash	0	0
redis.arrays.connecttimeout	0	0
redis.arrays.consistent	0	0
redis.arrays.distributor	no value	no value
redis.arrays.functions	no value	no value
redis.arrays.hosts	no value	no value
redis.arrays.index	0	0
redis.arrays.lazyconnect	0	0
redis.arrays.names	no value	no value
redis.arrays.pconnect	0	0
redis.arrays.previous	no value	no value
redis.arrays.readtimeout	0	0
redis.arrays.retryinterval	0	0
redis.clusters.auth	no value	no value
redis.clusters.cache_slots	0	0
redis.clusters.persistent	0	0
redis.clusters.read_timeout	0	0
redis.clusters.seeds	no value	no value
redis.clusters.timeout	0	0
redis.pconnect.connection_limit	0	0
redis.pconnect.echo_check_liveness	1	1
redis.pconnect.pool_detect_dirty	0	0
redis.pconnect.pool_pattern	no value	no value
redis.pconnect.pool_poll_timeout	0	0
redis.pconnect.pooling_enabled	1	1
redis.session.compression	none	none
redis.session.compression_level	3	3
redis.session.early_refresh	0	0
redis.session.lock_expire	0	0
redis.session.lock_failure_readonly	0	0
redis.session.lock_retries	100	100
redis.session.lock_wait_time	20000	20000
redis.session.locking_enabled	0	0
Reflection
Reflection	enabled
session
Session Support	enabled
Registered save handlers	files user redis rediscluster
Registered serializer handlers	php_serialize php php_binary igbinary msgpack
Directive	Local Value	Master Value
session.auto_start	Off	Off
session.cache_expire	180	180
session.cache_limiter	nocache	nocache
session.cookie_domain	no value	no value
session.cookie_httponly	Off	Off
session.cookie_lifetime	0	0
session.cookie_partitioned	Off	Off
session.cookie_path	/	/
session.cookie_samesite	no value	no value
session.cookie_secure	Off	Off
session.gc_divisor	1000	1000
session.gc_maxlifetime	1440	1440
session.gc_probability	1	1
session.lazy_write	On	On
session.name	PHPSESSID	PHPSESSID
session.referer_check	no value	no value
session.save_handler	files	files
session.save_path	/tmp	/tmp
session.serialize_handler	php	php
session.sid_bits_per_character	4	4
session.sid_length	32	32
session.upload_progress.cleanup	On	On
session.upload_progress.enabled	On	On
session.upload_progress.freq	1%	1%
session.upload_progress.min_freq	1	1
session.upload_progress.name	PHP_SESSION_UPLOAD_PROGRESS	PHP_SESSION_UPLOAD_PROGRESS
session.upload_progress.prefix	upload_progress_	upload_progress_
session.use_cookies	On	On
session.use_only_cookies	On	On
session.use_strict_mode	Off	Off
session.use_trans_sid	Off	Off
shmop
shmop support	enabled
SimpleXML
SimpleXML support	enabled
Schema support	enabled
soap
Soap Client	enabled
Soap Server	enabled
Directive	Local Value	Master Value
soap.wsdl_cache	1	1
soap.wsdl_cache_dir	/tmp	/tmp
soap.wsdl_cache_enabled	On	On
soap.wsdl_cache_limit	5	5
soap.wsdl_cache_ttl	86400	86400
sockets
Sockets Support	enabled
sodium
sodium support	enabled
libsodium headers version	1.0.18
libsodium library version	1.0.18
SPL
SPL support	enabled
Interfaces	OuterIterator, RecursiveIterator, SeekableIterator, SplObserver, SplSubject
Classes	AppendIterator, ArrayIterator, ArrayObject, BadFunctionCallException, BadMethodCallException, CachingIterator, CallbackFilterIterator, DirectoryIterator, DomainException, EmptyIterator, FilesystemIterator, FilterIterator, GlobIterator, InfiniteIterator, InvalidArgumentException, IteratorIterator, LengthException, LimitIterator, LogicException, MultipleIterator, NoRewindIterator, OutOfBoundsException, OutOfRangeException, OverflowException, ParentIterator, RangeException, RecursiveArrayIterator, RecursiveCachingIterator, RecursiveCallbackFilterIterator, RecursiveDirectoryIterator, RecursiveFilterIterator, RecursiveIteratorIterator, RecursiveRegexIterator, RecursiveTreeIterator, RegexIterator, RuntimeException, SplDoublyLinkedList, SplFileInfo, SplFileObject, SplFixedArray, SplHeap, SplMinHeap, SplMaxHeap, SplObjectStorage, SplPriorityQueue, SplQueue, SplStack, SplTempFileObject, UnderflowException, UnexpectedValueException
sqlite3
SQLite3 support	enabled
SQLite Library	3.53.4
Directive	Local Value	Master Value
sqlite3.defensive	On	On
sqlite3.extension_dir	no value	no value
standard
Dynamic Library Support	enabled
Path to sendmail	/usr/sbin/sendmail -t -i
Directive	Local Value	Master Value
assert.active	On	On
assert.bail	Off	Off
assert.callback	no value	no value
assert.exception	On	On
assert.warning	On	On
auto_detect_line_endings	Off	Off
default_socket_timeout	60	60
from	no value	no value
session.trans_sid_hosts	no value	no value
session.trans_sid_tags	a=href,area=href,frame=src,form=	a=href,area=href,frame=src,form=
unserialize_max_depth	4096	4096
url_rewriter.hosts	no value	no value
url_rewriter.tags	form=	form=
user_agent	no value	no value
tidy
Tidy support	enabled
libTidy Version	5.8.0
libTidy Release	2021/07/10
Directive	Local Value	Master Value
tidy.clean_output	Off	Off
tidy.default_config	no value	no value
timezonedb
Alternative Timezone Database	enabled
Timezone Database Version	2026.3
tokenizer
Tokenizer Support	enabled
uri
URI support	active
uriparser bundled version	1.0.2
xml
XML Support	active
XML Namespace Support	active
libxml2 Version	2.10.2
xmlreader
XMLReader	enabled
xmlrpc
XMLRPC extension version	1.0.0RC3
core library version	xmlrpc-epi v. 0.51
author	Dan Libby
homepage	http://xmlrpc-epi.sourceforge.net
open sourced by	Epinions.com
xmlwriter
XMLWriter	enabled
xsl
XSL	enabled
libxslt Version	1.1.34
libxslt compiled against libxml Version	2.9.13
EXSLT	enabled
libexslt Version	1.1.34
Zend OPcache
Opcode Caching	Up and Running
Optimization	Enabled
SHM Cache	Enabled
File Cache	Disabled
JIT	Disabled
Startup	OK
Shared memory model	mmap
Cache hits	54498488
Cache misses	3542
Used memory	142720144
Free memory	58606448
Wasted memory	0
Interned Strings Used memory	16777208
Interned Strings Free memory	8
Cached scripts	3542
Cached keys	3542
Max keys	7963
OOM restarts	0
Hash keys restarts	0
Manual restarts	0
Start time	2026-08-14T22:55:01+0300
Last restart time	none
Last force restart time	none
Directive	Local Value	Master Value
opcache.blacklist_filename	no value	no value
opcache.dups_fix	Off	Off
opcache.enable	On	On
opcache.enable_cli	Off	Off
opcache.enable_file_override	Off	Off
opcache.error_log	no value	no value
opcache.file_cache	no value	no value
opcache.file_cache_consistency_checks	On	On
opcache.file_cache_only	Off	Off
opcache.file_cache_read_only	Off	Off
opcache.file_update_protection	2	2
opcache.force_restart_timeout	180	180
opcache.huge_code_pages	Off	Off
opcache.interned_strings_buffer	16	16
opcache.jit	disable	disable
opcache.jit_bisect_limit	0	0
opcache.jit_blacklist_root_trace	16	16
opcache.jit_blacklist_side_trace	8	8
opcache.jit_buffer_size	64M	64M
opcache.jit_debug	0	0
opcache.jit_hot_func	127	127
opcache.jit_hot_loop	61	61
opcache.jit_hot_return	8	8
opcache.jit_hot_side_exit	8	8
opcache.jit_max_exit_counters	8192	8192
opcache.jit_max_loop_unrolls	8	8
opcache.jit_max_polymorphic_calls	2	2
opcache.jit_max_recursive_calls	2	2
opcache.jit_max_recursive_returns	2	2
opcache.jit_max_root_traces	1024	1024
opcache.jit_max_side_traces	128	128
opcache.jit_max_trace_length	1024	1024
opcache.jit_prof_threshold	0.005	0.005
opcache.lockfile_path	/tmp	/tmp
opcache.log_verbosity_level	1	1
opcache.max_accelerated_files	7963	7963
opcache.max_file_size	0	0
opcache.max_wasted_percentage	5	5
opcache.memory_consumption	192	192
opcache.opt_debug_level	0	0
opcache.optimization_level	0x7FFEBFFF	0x7FFEBFFF
opcache.preferred_memory_model	no value	no value
opcache.preload	no value	no value
opcache.preload_user	no value	no value
opcache.protect_memory	Off	Off
opcache.record_warnings	Off	Off
opcache.restrict_api	no value	no value
opcache.revalidate_freq	2	2
opcache.revalidate_path	On	On
opcache.save_comments	On	On
opcache.use_cwd	On	On
opcache.validate_permission	On	On
opcache.validate_root	Off	Off
opcache.validate_timestamps	On	On
zip
Zip	enabled
Zip version	1.22.8
Libzip version	1.7.3
BZIP2 compression	Yes
XZ compression	Yes
ZSTD compression	No
AES-128 encryption	Yes
AES-192 encryption	Yes
AES-256 encryption	Yes
zlib
ZLib Support	enabled
Stream Wrapper	compress.zlib://
Stream Filter	zlib.inflate, zlib.deflate
Compiled Version	1.2.11
Linked Version	1.2.11
Directive	Local Value	Master Value
zlib.output_compression	Off	Off
zlib.output_compression_level	-1	-1
zlib.output_handler	no value	no value
Additional Modules
Module Name
litespeed
Environment
Variable	Value
PATH	/usr/local/bin:/bin:/usr/bin
DOCUMENT_ROOT	/home/r140100buzo/public_html-nunta.buzonnect.ro
PHP Variables
Variable	Value
$_COOKIE['PHPSESSID']	e78591b79696f94d7ea93ec1dfdf3fc5
$_SERVER['PATH']	/usr/local/bin:/bin:/usr/bin
$_SERVER['DOCUMENT_ROOT']	/home/r140100buzo/public_html-nunta.buzonnect.ro
$_SERVER['HTTP_ACCEPT']	text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7
$_SERVER['HTTP_ACCEPT_ENCODING']	gzip, deflate, br, zstd
$_SERVER['HTTP_ACCEPT_LANGUAGE']	ro-RO,ro;q=0.9,en-US;q=0.8,en;q=0.7
$_SERVER['HTTP_COOKIE']	PHPSESSID=e78591b79696f94d7ea93ec1dfdf3fc5
$_SERVER['HTTP_HOST']	nunta.buzonnect.ro
$_SERVER['HTTP_USER_AGENT']	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36
$_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS']	1
$_SERVER['HTTP_SEC_CH_UA']	"Not=A?Brand";v="99", "Google Chrome";v="151", "Chromium";v="151"
$_SERVER['HTTP_SEC_CH_UA_MOBILE']	?0
$_SERVER['HTTP_SEC_CH_UA_PLATFORM']	"Windows"
$_SERVER['HTTP_SEC_FETCH_SITE']	none
$_SERVER['HTTP_SEC_FETCH_MODE']	navigate
$_SERVER['HTTP_SEC_FETCH_USER']	?1
$_SERVER['HTTP_SEC_FETCH_DEST']	document
$_SERVER['HTTP_PRIORITY']	u=0, i
$_SERVER['REMOTE_ADDR']	86.120.126.165
$_SERVER['REMOTE_PORT']	54664
$_SERVER['SERVER_ADDR']	89.42.218.11
$_SERVER['SERVER_NAME']	nunta.buzonnect.ro
$_SERVER['SERVER_ADMIN']	webmaster@nunta.buzonnect.ro
$_SERVER['SERVER_PORT']	443
$_SERVER['REQUEST_SCHEME']	https
$_SERVER['REQUEST_URI']	/info.php
$_SERVER['HTTPS']	on
$_SERVER['CRAWLER_LOAD_LIMIT']	64
$_SERVER['X_SPDY']	HTTP2
$_SERVER['SSL_PROTOCOL']	TLSv1.3
$_SERVER['SSL_CIPHER']	TLS_AES_128_GCM_SHA256
$_SERVER['SSL_CIPHER_USEKEYSIZE']	128
$_SERVER['SSL_CIPHER_ALGKEYSIZE']	128
$_SERVER['SCRIPT_FILENAME']	/home/r140100buzo/public_html-nunta.buzonnect.ro/info.php
$_SERVER['QUERY_STRING']	no value
$_SERVER['SCRIPT_URI']	https://nunta.buzonnect.ro/info.php
$_SERVER['SCRIPT_URL']	/info.php
$_SERVER['SCRIPT_NAME']	/info.php
$_SERVER['SERVER_PROTOCOL']	HTTP/1.1
$_SERVER['SERVER_SOFTWARE']	LiteSpeed
$_SERVER['REQUEST_METHOD']	GET
$_SERVER['X-LSCACHE']	on,crawler,esi,combine
$_SERVER['PHP_SELF']	/info.php
$_SERVER['REQUEST_TIME_FLOAT']	1786797937.9714
$_SERVER['REQUEST_TIME']	1786797937
PHP Credits
PHP Group
Thies C. Arntzen, Stig Bakken, Shane Caraveo, Andi Gutmans, Rasmus Lerdorf, Sam Ruby, Sascha Schumann, Zeev Suraski, Jim Winstead, Andrei Zmievski
Language Design & Concept
Andi Gutmans, Rasmus Lerdorf, Zeev Suraski, Marcus Boerger
PHP Authors
Contribution	Authors
Zend Scripting Language Engine	Andi Gutmans, Zeev Suraski, Stanislav Malyshev, Marcus Boerger, Dmitry Stogov, Xinchen Hui, Nikita Popov
Extension Module API	Andi Gutmans, Zeev Suraski, Andrei Zmievski
UNIX Build and Modularization	Stig Bakken, Sascha Schumann, Jani Taskinen, Peter Kokot
Windows Support	Shane Caraveo, Zeev Suraski, Wez Furlong, Pierre-Alain Joye, Anatol Belski, Kalle Sommer Nielsen
Server API (SAPI) Abstraction Layer	Andi Gutmans, Shane Caraveo, Zeev Suraski
Streams Abstraction Layer	Wez Furlong, Sara Golemon
PHP Data Objects Layer	Wez Furlong, Marcus Boerger, Sterling Hughes, George Schlossnagle, Ilia Alshanetsky
Output Handler	Zeev Suraski, Thies C. Arntzen, Marcus Boerger, Michael Wallner
Consistent 64 bit support	Anthony Ferrara, Anatol Belski
SAPI Modules
Contribution	Authors
Apache 2 Handler	Ian Holsman, Justin Erenkrantz (based on Apache 2 Filter code)
CGI / FastCGI	Rasmus Lerdorf, Stig Bakken, Shane Caraveo, Dmitry Stogov
CLI	Edin Kadribasic, Marcus Boerger, Johannes Schlueter, Moriyoshi Koizumi, Xinchen Hui
Embed	Edin Kadribasic
FastCGI Process Manager	Andrei Nigmatulin, dreamcat4, Antony Dovgal, Jerome Loyet
litespeed	George Wang
phpdbg	Felipe Pena, Joe Watkins, Bob Weinand
Module Authors
Module	Authors
BC Math	Andi Gutmans
Bzip2	Sterling Hughes
Calendar	Shane Caraveo, Colin Viebrock, Hartmut Holzgraefe, Wez Furlong
COM and .Net	Wez Furlong
ctype	Hartmut Holzgraefe
cURL	Sterling Hughes
Date/Time Support	Derick Rethans
DB-LIB (MS SQL, Sybase)	Wez Furlong, Frank M. Kromann, Adam Baratz
DBA	Sascha Schumann, Marcus Boerger
DOM	Christian Stocker, Rob Richards, Marcus Boerger, Nora Dossche
enchant	Pierre-Alain Joye, Ilia Alshanetsky
EXIF	Rasmus Lerdorf, Marcus Boerger
FFI	Dmitry Stogov
fileinfo	Ilia Alshanetsky, Pierre Alain Joye, Scott MacVicar, Derick Rethans, Anatol Belski
Firebird driver for PDO	Ard Biesheuvel
FTP	Stefan Esser, Andrew Skalski
GD imaging	Rasmus Lerdorf, Stig Bakken, Jim Winstead, Jouni Ahto, Ilia Alshanetsky, Pierre-Alain Joye, Marcus Boerger, Mark Randall
GetText	Alex Plotnick
GNU GMP support	Stanislav Malyshev
Iconv	Rui Hirokawa, Stig Bakken, Moriyoshi Koizumi
Input Filter	Rasmus Lerdorf, Derick Rethans, Pierre-Alain Joye, Ilia Alshanetsky
Internationalization	Ed Batutis, Vladimir Iordanov, Dmitry Lakhtyuk, Stanislav Malyshev, Vadim Savchuk, Kirti Velankar
JSON	Jakub Zelenka, Omar Kilani, Scott MacVicar
LDAP	Amitay Isaacs, Eric Warnke, Rasmus Lerdorf, Gerrit Thomson, Stig Venaas
LIBXML	Christian Stocker, Rob Richards, Marcus Boerger, Wez Furlong, Shane Caraveo
Multibyte String Functions	Tsukada Takuya, Rui Hirokawa
MySQL driver for PDO	George Schlossnagle, Wez Furlong, Ilia Alshanetsky, Johannes Schlueter
MySQLi	Zak Greant, Georg Richter, Andrey Hristov, Ulf Wendel
MySQLnd	Andrey Hristov, Ulf Wendel, Georg Richter, Johannes Schlüter
ODBC driver for PDO	Wez Furlong
ODBC	Stig Bakken, Andreas Karajannis, Frank M. Kromann, Daniel R. Kalowsky
Opcache	Andi Gutmans, Zeev Suraski, Stanislav Malyshev, Dmitry Stogov, Xinchen Hui
OpenSSL	Stig Venaas, Wez Furlong, Sascha Kettler, Scott MacVicar, Eliot Lear
pcntl	Jason Greene, Arnaud Le Blanc
Perl Compatible Regexps	Andrei Zmievski
PHP Archive	Gregory Beaver, Marcus Boerger
PHP Data Objects	Wez Furlong, Marcus Boerger, Sterling Hughes, George Schlossnagle, Ilia Alshanetsky
PHP hash	Sara Golemon, Rasmus Lerdorf, Stefan Esser, Michael Wallner, Scott MacVicar
Posix	Kristian Koehntopp
PostgreSQL driver for PDO	Edin Kadribasic, Ilia Alshanetsky
PostgreSQL	Jouni Ahto, Zeev Suraski, Yasuo Ohgaki, Chris Kings-Lynne
random	Go Kudo, Tim Düsterhus, Guilliam Xavier, Christoph M. Becker, Jakub Zelenka, Bob Weinand, Máté Kocsis, and Original RNG implementators
Readline	Thies C. Arntzen
Reflection	Marcus Boerger, Timm Friebe, George Schlossnagle, Andrei Zmievski, Johannes Schlueter
Sessions	Sascha Schumann, Andrei Zmievski
Shared Memory Operations	Slava Poliakov, Ilia Alshanetsky
SimpleXML	Sterling Hughes, Marcus Boerger, Rob Richards
SNMP	Rasmus Lerdorf, Harrie Hazewinkel, Mike Jackson, Steven Lawrance, Johann Hanne, Boris Lytochkin
SOAP	Brad Lafountain, Shane Caraveo, Dmitry Stogov
Sockets	Chris Vandomelen, Sterling Hughes, Daniel Beulshausen, Jason Greene
Sodium	Frank Denis
SPL	Marcus Boerger, Etienne Kneuss
SQLite 3.x driver for PDO	Wez Furlong
SQLite3	Scott MacVicar, Ilia Alshanetsky, Brad Dewar
System V Message based IPC	Wez Furlong
System V Semaphores	Tom May
System V Shared Memory	Christian Cartus
tidy	John Coggeshall, Ilia Alshanetsky
tokenizer	Andrei Zmievski, Johannes Schlueter
uri	Máté Kocsis, Tim Düsterhus, Ignace Nyamagana Butera, Arnaud Le Blanc, Dennis Snell, Nora Dossche, Nicolas Grekas
XML	Stig Bakken, Thies C. Arntzen, Sterling Hughes
XMLReader	Rob Richards
XMLWriter	Rob Richards, Pierre-Alain Joye
XSL	Christian Stocker, Rob Richards
Zip	Pierre-Alain Joye, Remi Collet
Zlib	Rasmus Lerdorf, Stefan Roehrich, Zeev Suraski, Jade Nicoletti, Michael Wallner
PHP Documentation
Authors	Mehdi Achour, Friedhelm Betz, Antony Dovgal, Nuno Lopes, Hannes Magnusson, Philip Olson, Georg Richter, Damien Seguy, Jakub Vrana, Adam Harvey
Editor	Peter Cowburn
User Note Maintainers	Daniel P. Brown, Thiago Henrique Pojda
Other Contributors	Previously active authors, editors and other contributors are listed in the manual.
PHP Quality Assurance Team
Ilia Alshanetsky, Joerg Behrens, Antony Dovgal, Stefan Esser, Moriyoshi Koizumi, Magnus Maatta, Sebastian Nohn, Derick Rethans, Melvyn Sopacua, Pierre-Alain Joye, Dmitry Stogov, Felipe Pena, David Soria Parra, Stanislav Malyshev, Julien Pauli, Stephen Zarkos, Anatol Belski, Remi Collet, Ferenc Kovacs
Websites and Infrastructure team
PHP Websites Team	Rasmus Lerdorf, Hannes Magnusson, Philip Olson, Lukas Kahwe Smith, Pierre-Alain Joye, Kalle Sommer Nielsen, Peter Cowburn, Adam Harvey, Ferenc Kovacs, Levi Morrison
Event Maintainers	Damien Seguy, Daniel P. Brown
Network Infrastructure	Daniel P. Brown
Windows Infrastructure	Alex Schoenmaker
PHP License
This program is free software; you can redistribute it and/or modify it under the terms of the PHP License as published by the PHP Group and included in the distribution in the file: LICENSE

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

If you did not receive a copy of the PHP license, or have any questions about PHP licensing, please contact license@php.net.