# PHP-NUT-Client
>🚧 Work In Progress!

PhpNutClient abstracts the connection the the NUT server. It's a wrapper around the [NUT network protocol](https://networkupstools.org/docs/developer-guide.chunked/ar01s09.html). It can be integrated into other PHP programs to access NUT's upsd data server.

NutExceptions are raised when a NUT network protocol error occurs. They aim to provide full support for all error messages, as well as a dynamic and clear description.

## Testing
A Dockerfile to build a testing environment is provided, which includes a dummy UPS.

## License
This is open-sourced software licensed under the [GNU GPL license](https://www.gnu.org/licenses).
