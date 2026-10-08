// Bundle-check the dashboard module without a host app: `bin/check-js` (any Vite 7+ on PATH or VITE_BIN).
export default {
    logLevel: 'warn',
    build: {
        outDir: 'node_modules/.ops-check',
        emptyOutDir: true,
        minify: false,
        lib: { entry: 'resources/js/ops/index.js', formats: ['es'], fileName: 'ops' },
        rollupOptions: { external: (id) => !id.startsWith('.') && !id.startsWith('/') },
    },
}
