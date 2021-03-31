# Shell

This is a simple PHP shell.
It allows you to perform basic file system and terminal operations,
yet it goes much further than most other shells.

## Usage

This shell can be used for simple server management.

If you plan on using this shell on servers you hijacked, please don't.
There are much more useful shells out there that are contained in a single file.
This shell will not try to work its way around any restrictions put in place by the server.

## Features

Quick overview of the features.
Details are in the chapters below

- General: Cinematic dark mode hacker look (aka. green monospace font on black background)
- General: Minimal JS usage
- General: Settings for customizations and performance
- General: Read basic server and PHP configuration
- Terminal: Perform terminal operations
- Terminal: Command aliasing
- Terminal: Capture stdout and stderr
- Shell: Perform basic file and directory operations
- Shell: File upload and download
- Shell: File editing and binary data viewer
- Shell: Detect and display various media types (image, audio, video, sqlite)
- Shell: Media preview
- Shell: Zip and gzip support
- Shell: Recusrive directory operations
- Security: Unlike many other shells, actually good password hashing
- Security: File encryption
- Performance: Download files with range support (useful for audio/video)
- Performance: Sending and processing of HTTP caching headers

### Basic server information

Using the "info" button in the main menu will show basic server information.
This is mostly equivalent to `phpinfo()` but split across tabs for readability.
It also shows some additional information in "Other", the php configuration in "INI",
and your shell configuration under "Config".

General system information is contained in the footer on every tab.

### Shell

The shell is the most complex and versatile feature.
It's likely where you spend most of your time.

In it's most basic form, it's organized as follows from top to bottom:

At the very top is a link to get back to the main menu.

Below that (windows only) is a list of all drives.
Clicking on a drive will switch to that drives root directory.

Below the disk list is the URL field.
The shell tries to detect what the URL of the currently viewed directory is.
The URL will not be there if it can't be determined.
This can happen if the shell is unable to detect the web root directory,
or if you're viewing files outside of the web root.
In that case, the guessed web root is shown.

Below the URL label is a text box where you can see your current directory.
It allows you to directly key in any directory you want to go to.
Next to it is a button that directly opens a terminal in the currently viewed directory.

Below that are buttons to switch the views. You can select between the details view (default)
and the thumbnail view. The thumbnail view will show images and allow you to play media files directly
without having to open the file details.

Below that is the directory view.
The directory view is sorted as follows:

1. The ".." directory
2. Subdirectories (alphabetical, ignoring case)
3. Files (alphabetical, ignoring case)

Clicking on a directory will enter it,
clicking on a file will show its details.

You also get direct access to common file/directory management features.

At the very bottom are fields to perform various operations:

Creating directories:
You can create mutliple directories at once, so typing `test/a/b` will create all 3 directories if necessary.

Creating files:
This simply creates an empty file so you can edit it

Transfer files:
You can download files to the server from other locations by entering the URL.
In most cases only http(s) and ftp are supported.

Upload files:
You can upload files too.
The dialog is programmed to allow multiple files to be uploaded at once.
The size restrictions imposed by PHP are shown below.
Be aware that the webserver itself may also have additional restrictions in place.

### File details

Clicking on a file in the shell will open the file details.
The details vary depending on the type.
Basic operations that are always available:

- Delete
- Rename/Copy/Move
- Compress to zip
- Compress to gzip
- Encrypt/Decrypt

#### Empty files

These are treated like text files

#### Text files

These can be directly edited using the text box

#### Image files

Images are shown as a preview.
Clicking on the image will load the full resolution.
Regardless of image resolution, the image will not be wider than your browser window.

#### Audio/Video files

Audio and video files can be directly played in your browser.
Note that browsers only support a small subset of media files.

#### Database

If a file is detected as SQLite,
the first few rows of every table are shown.

#### Zip

For zip files, the directory listing is shown.
Links to extract it to the current directory or a subdirectory with the zip file name are provided.
Be careful, this will overwrite existing files.

#### GZip

GZip files behave like binary files (see below).
The hex view will show the decompressed contents.

#### Encrypted files

Files encrypted with this shell behave like binary files.
There is a link to offer decryption.

#### Binary file

Binary files are shown as a hex dump of the first few kilobytes.
There is an option provided to override the detected type.
So for example if the type detection fails to recognize an image,
you can override it to pretend it is an image.
It will likely not work though since the file detection is usually reliable enough.
Auto detection does occasionally fail on MP3 files with metadata and requires an override.

### Compression

You can compress and decompress zip and gzip files.
gzip is common on linux for single files,
zip is common worldwide and can handle multiple files.

Compressing to zip is offered next to every file/directory entry.
Compressing to gzip as well as decompressing zip/gzip files is offered only in the file detail view only.

When you compress, the shell will add `.zip` or `.gz` to the file/directory you want to compress.
If a file with that name already exists, a number is added to the name until a free name is found.

Note: The shell will refuse to compress already compressed files because it's generally useless.
This restriction will not apply if you compress an entire directory.

The shell will not decompress archives recursively.

### Encrypt / Decrypt

This is offered next to every file in the directory listing and on the file details page.

It allows you to encrypt and decrypt files using a password and a chosen algorithm.
The shell defaults to `aes-256-gcm` because it's very secure, but you can change it.

You can also set the name of the file.
It defaults to the current file name, meaning it's processed in-place.

You can't encrypt entire directories.
If you want to do that, zip the directory first, then encrypt the zip file.

#### Algorithms

Unsafe algorithms are not shown unless you change this in the settings.
If you do this, you will get the list completely unfiltered,
meaning it may include algorithms that don't work at all using the crypto implementation of this shell.

Not all algorithms provide authentication.
This means that if something happens to the encrypted data, you can't reliably detect it.
The shell tries to avoid this by including a sha256 hmac.
If you implement your own tool to use the encrypted file format in use by this shell,
always validate the hmac.

The shell automatically detects encrypted files and will pick the encrypt/decrypt operation automatically.
If the file is encrypted, additional details are shown (such as the algorithm).

#### Key derivation

*You only need to know this if you plan on writing your own tool.*

Key derivation is done using PBKDF2 and a custom salt.
The number of rounds defaults to 50000 but you can change it.
The shell will still be able to decrypt files after you change the number,
because the original is encoded in the header.

The hash for this algorithm is `sha1`.

Note: Nothing stops you from using the IV as salt too,
but be aware that not all algorithms have an IV.

#### Encrypted file format

*You only need to know this if you plan on writing your own tool.*

The format is very simple.
Apart from the "magic" constant, binary data is length prefixed using big endian integers(shown as `LP(X)`).
The number in parenthesis tells the number of bytes the integer is encoded into.
So for an LP(2) for example,
you would read a two byte big endian encoded integer.
That integer speficies how many bytes to read directly after the number to obtain the data of the header field.

Header:

1. string "CRYPT"
2. LP(2) Cipher
3. LP(2) IV
4. LP(2) Salt
5. LP(2) Hmac
6. int(4) Rounds
7. LP(8) Data
8. LP(2) Tag

Note: header fields are not named in the file and thus you cannot change the field order.

##### Cipher

This is the cipher in use for the file.
It's the value from the mode drop down.

##### IV

This is the initialization vector.
**The IV is not considered secret and you do not need to protect it.**

The IV ensures that if you encrypt the same file using the same password multiple times,
you get a different output each time.
This prevents people from guessing what the source file was and
lessens the problem with password reuse.

The length of this depends on the cryptographic algorithm and cannot be freely chosen.
Not all algorithms have an IV (ecb mode for example).
If they don't, they're usually not considered secure.
The data is random and has no form.

##### Salt

This is the salt value in use for the key derivation function.
This makes sure that even if you use the same password every time,
the encrypted data will be different.

The length can be freely chosen, this implementation uses 32 bytes.
The data is random and has no form.

##### Hmac

This is a cryptographic hash that is used to verify file integrity.
Many cryptographic algorithms, for example AES in CBC mode (very commonly used),
do not provide a way to detect if the encrypted data was accidentally (or maliciously) altered.

To combat this, this shell employs a hmac using `sha256`.
A hmac is like a hash but it has a secret component to it.
This allows us to alter the result, even if we input the same file over and over again.
It stops people from guessing the original file based on file hash.

The key to the hmac function is the derived key from the password function.
Because the password is not known to an attacker,
they can't alter the hash in a meaningful way to match the output after they change the encrypted data.

hmac parameters:

- Hash: sha256
- Data: source file content (not the hash of the file, the raw binary contents)
- Key: derived key from salt and password.

##### Rounds

This is the integer that is supplied as the number of iterations into the pbkdf2 key derivation function.

Note: This is not a length prefixed byte data like the other fields, it's just the integer.
The integer is 4 bytes and will overflow if the number is above 2^31 and you run a 32 bit PHP installation.

##### Data

This is the encrypted data.

**CAUTION:**

The integer for this field is 8 bytes long, or 64 bits.
Note that if you encrypt a file over 2^31 bytes in size (2 GiB),
the number will become corrupted on 32 bit PHP installations.

##### Tag

This is the authentication tag.
It might be of zero length if the algorithm doesn't supports authentication.

##### Key

The key is never written to the file and thus not part of the header structure,
but it may be present in the header array in memory (not the file) after encrypt/decrypt operations.
If you decide to do something with the header array, make sure to never store or reveal the key.
To be safe, you should unset the value manually.

### Terminal

The terminal is as simple as you would imagine.
You type a command, and it shows the output.
The command is run in a real terminal,
so you can use terminal internals and I/O redirection and pipes.

Be aware that the terminal is closed after every command,
which means that environment variables will not persist.

You can define custom aliases in the settings.
These are shown in a drop down as you type.

#### Input

If a command is run, the shell will immediately close the stdin handle.
This causes most programs that wait for user inputs to fail.
Some obtain the input in a different way,
those will usually hang until php times out.
This will leave the virtual terminal open indefinitely.
If you have many commands that fail,
you will eventually end up with a large number of unusable terminals.
In those cases you may want to restart your webserver.

#### Output

The terminal will read stdout and stderr separately and display them in their appropriate text boxes.

You can disable reading of stderr in the settings.
This is useful if you run commands that log a metric ton to stderr.

#### PHP commands

If you prefix your command with `php:`, it will be run in an `eval()` instead.
To easily run more than one command, store your commands in a file,
and then use `php:require("filename.php");`

#### "cd" command

The cd command is special.
Because the terminal is closed after every command,
it is generally useless.
The shell will detect simple usage of `cd <dir>` and will change the actual directory.

### Settings

The settings allow you to not only change settings,
but also define terminal aliases and the shell password.

#### Password

The shell has no user name, just a password.
You can change it here, which will immediately discard your session.

#### Options

Various options are provided

##### Show unsafe/invalid cryptographic algorithms

This will show all algorithms that openssl supports.

This includes:

- Unsafe algorithms (such as des or rc4)
- Unsafe modes (such as xts and ecb)
- Invalid algorithms (such as seed-...)

Unless you have very good reason, you should not enable this.

##### Strip comments in PHP ini

This will strip INI comments and empty lines from the PHP.ini file in the system information view.
Enabling this essentially reduces the ini to only the parts that are currently in use.

Note: this is only a display option and will not actually change the real ini file.

##### Detect file type by file extension only

Enabling this yields massive performance enhancements in directories with many files,
and on network drives.

However, it means the shell will not show images that have a wrong file extension as such.
You can always view individual files with a wrong extension by clicking on them.

##### Don't count directory entries or enumerate last modified time

Normally, the shell will show the number of **direct** entries in a subdirectory.
It also shows the last modified time of all entries.

Both require additional file system operations,
which will take a long time on network drives or large directories.

Enabling this setting makes the shell skip those features.

Note: Even if you don't enable this,
the shell will eventually stop enumerating those properties if the listing already took a long time.

##### Disable recursion

If you have a very slow server,
you can enable this setting to make the shell reject recursive operations.
This means for example that you can only delete directories that are empty.

##### Ignore error and input streams in shell commands

Internally, this switches from `proc_open` to `exec`.

This speeds up terminal commands that write a lot to stderr, because it's ignored.
It also makes PHP ignore the stdin stream, meaning a program that prompts for input hangs indefinitely.

Check the "Terminal / Input" chapter to see what this means.

#### Aliases

You can define aliases, one per line.
An alias is in the format `alias=command`.

Aliases can't contain spaces or `=`.
Aliases can't reference other aliases.
Aliases are case insensitive regardless of your operating system.
Any argument you apply to an alias will be appended to the end.

Example: The alias `ls=dir /W` if run with the argument "test" will expand to `dir /W test`.

If supported by your browser, aliases are suggested to you as you type in the terminal.
Most browsers also display a drop down if you click into the command line field while it already has focus.

## Installation

Drop all files into a directory on your web server, then access `shell.php` to get started.
You will be asked to set your password.

You can either download the current release from https://github.com/AyrA/Shell/archive/refs/heads/master.zip
or do a `git clone https://github.com/AyrA/Shell.git`.
There's no release process in this application, the source is uses as-is.
Therefore you will find that the "Releases" section will stay mostly empty.
I may add tags in the future.

### Custom settings file

By default, your settings are stored in a file named `inc.config.php`
in the directory the shell is in.
This means you need to grant the web server write permissions into the shell directory.
If you don't want that, change the `CONFIG_FILE` constant in `shell.php` to a different path.

Note: The config file is not actually a PHP file.
The shell just abuses the php file to hide the configuration.
Ensure to not accidentally expose the configuration to the general public.
While we do hash your password using a very safe method and it's unlikely someone finds your password,
having access to the config file allows people to reconstruct the authentication cookie
and log into the shell without a password.

### Public servers

If your web server is public,
drop the shell into a protected directory that only you have access to until you've set a password.

On an apache server, the easiest way to do this is to add a `.htaccess` file.
Content: `Require ip 0.0.0.0`.
Replace 0.0.0.0 with your IP address (see https://ip.ayra.ch for example).

You can also use `Require ip local` to restrict the shell to the local machine of your web server.
If your server doesn't has a GUI,
you can use a command line web browser to access the shell.
JavaScript is optional for basic functionality.

Once you've set your password, you can use the shell to delete the `.htaccess` file if you want to.
