- [ ] /backend/.env has an environmental variable that defines the platform fee as a percent. trace this logic and change it to be in CIRX, and make sure its correctly integrated with the frontend/backend. 

2025-08-18T09:05:45.647205Z	Cloning repository...
2025-08-18T09:05:47.007263Z	From https://github.com/lessuselesss/cirx-swap-clean
2025-08-18T09:05:47.007787Z	 * branch            6dfc5845de424a3dcbd1d1f744152675cd2b0542 -> FETCH_HEAD
2025-08-18T09:05:47.007993Z	
2025-08-18T09:05:47.200811Z	HEAD is now at 6dfc584 feat: add 7th state for address validation and separate gradient animations
2025-08-18T09:05:47.201334Z	
2025-08-18T09:05:47.282698Z	
2025-08-18T09:05:47.283128Z	Using v2 root directory strategy
2025-08-18T09:05:47.304317Z	Success: Finished cloning repository files
2025-08-18T09:05:49.043746Z	Checking for configuration in a Wrangler configuration file (BETA)
2025-08-18T09:05:49.044468Z	
2025-08-18T09:05:49.046047Z	Found wrangler.toml file. Reading build configuration...
2025-08-18T09:05:49.05218Z	pages_build_output_dir: .output/public
2025-08-18T09:05:49.052299Z	Build environment variables: (none found)
2025-08-18T09:05:50.151062Z	Successfully read wrangler.toml file.
2025-08-18T09:05:50.219872Z	Detected the following tools from environment: npm@10.9.2, nodejs@22.16.0
2025-08-18T09:05:50.220576Z	Installing project dependencies: npm clean-install --progress=false
2025-08-18T09:05:57.02455Z	npm warn deprecated inflight@1.0.6: This module is not supported, and leaks memory. Do not use it. Check out lru-cache if you want a good and tested way to coalesce async requests by a key value, which is much more comprehensive and powerful.
2025-08-18T09:05:58.720665Z	npm warn deprecated @paulmillr/qr@0.2.1: The package is now available as "qr": npm install qr
2025-08-18T09:05:58.905109Z	npm warn deprecated glob@7.2.3: Glob versions prior to v9 are no longer supported
2025-08-18T09:06:51.78945Z	
2025-08-18T09:06:51.789804Z	> uniswapv3clone-frontend@1.0.0 postinstall
2025-08-18T09:06:51.78993Z	> nuxt prepare
2025-08-18T09:06:51.790111Z	
2025-08-18T09:06:53.076393Z	[info] [nuxt:tailwindcss] Using default Tailwind CSS file
2025-08-18T09:06:53.771119Z	[success] [nuxi] Types generated in .nuxt
2025-08-18T09:06:53.868978Z	
2025-08-18T09:06:53.869585Z	added 1395 packages, and audited 1397 packages in 1m
2025-08-18T09:06:53.869741Z	
2025-08-18T09:06:53.869849Z	273 packages are looking for funding
2025-08-18T09:06:53.869975Z	  run `npm fund` for details
2025-08-18T09:06:53.876453Z	
2025-08-18T09:06:53.876604Z	5 vulnerabilities (1 low, 4 high)
2025-08-18T09:06:53.876685Z	
2025-08-18T09:06:53.876749Z	To address issues that do not require attention, run:
2025-08-18T09:06:53.877019Z	  npm audit fix
2025-08-18T09:06:53.877112Z	
2025-08-18T09:06:53.877171Z	To address all issues (including breaking changes), run:
2025-08-18T09:06:53.87752Z	  npm audit fix --force
2025-08-18T09:06:53.87762Z	
2025-08-18T09:06:53.877755Z	Run `npm audit` for details.
2025-08-18T09:06:53.921739Z	Executing user command: npm run build
2025-08-18T09:06:54.398556Z	
2025-08-18T09:06:54.398805Z	> uniswapv3clone-frontend@1.0.0 build
2025-08-18T09:06:54.398909Z	> nuxt build
2025-08-18T09:06:54.399002Z	
2025-08-18T09:06:54.507856Z	[log] [nuxi] Nuxt 3.17.7 with Nitro 2.12.4
2025-08-18T09:06:55.409119Z	[info] [nuxt:tailwindcss] Using default Tailwind CSS file
2025-08-18T09:06:55.788214Z	[info] [nuxi] Building for Nitro preset: `static`
2025-08-18T09:06:56.796427Z	[info] Building client...
2025-08-18T09:06:56.808782Z	[info] [36mvite v6.3.5 [32mbuilding for production...[36m[39m
2025-08-18T09:06:56.837729Z	[info] transforming...
2025-08-18T09:07:00.650292Z	[info] [32m✓[39m 455 modules transformed.
2025-08-18T09:07:00.656955Z	[error] [31m✗[39m Build failed in 3.85s
2025-08-18T09:07:00.657558Z	[error] [nuxi] Nuxt Build Error: [31m[vite:load-fallback] Could not load /opt/buildhome/repo/ui/composables/useBackendApi.js (imported by pages/swap.vue): ENOENT: no such file or directory, open '/opt/buildhome/repo/ui/composables/useBackendApi.js'[39m
2025-08-18T09:07:00.657839Z	  at async open (node:internal/fs/promises:633:25)
2025-08-18T09:07:00.658034Z	  at async Object.readFile (node:internal/fs/promises:1237:14)
2025-08-18T09:07:00.658215Z	  at async Object.handler (node_modules/vite/dist/node/chunks/dep-DBxKXgDP.js:45843:27)
2025-08-18T09:07:00.658304Z	  at async PluginDriver.hookFirstAndGetPlugin (node_modules/rollup/dist/es/shared/node-entry.js:22185:28)
2025-08-18T09:07:00.658425Z	  at async node_modules/rollup/dist/es/shared/node-entry.js:21189:33
2025-08-18T09:07:00.658575Z	  at async Queue.work (node_modules/rollup/dist/es/shared/node-entry.js:22413:32)
2025-08-18T09:07:00.72923Z	Failed: Error while executing user command. Exited with error code: 1
2025-08-18T09:07:00.740433Z	Failed: build command exited with code: 1
2025-08-18T09:07:02.034559Z	Failed: error occurred while running build command