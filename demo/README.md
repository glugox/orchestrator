# Orchestrator Demo Sandbox

This directory hosts assets used by the sandbox bootstrap script. Running
`sandbox/boot.sh` will create a fresh Laravel application inside
`demo/laravel-app`, configure it to use the local orchestrator package and
require the bundled **Hello World** module that lives under
`demo/modules/hello-world`.

The bundled module exposes a small JSON API response at
`/api/hello-world` once the module is installed and enabled.
