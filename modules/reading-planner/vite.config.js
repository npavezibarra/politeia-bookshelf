import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
	plugins: [react()],
	build: {
		outDir: 'assets',
		emptyOutDir: false,
		rollupOptions: {
			input: {
				'reading-plan-entry': 'assets/js/reading-plan-entry.jsx',
			},
			output: {
				entryFileNames: 'js/reading-plan-entry.js',
				chunkFileNames: 'js/[name].js',
				assetFileNames: (assetInfo) => {
					if (assetInfo.name && assetInfo.name.endsWith('.css')) {
						return 'css/reading-plan-app.css';
					}
					return 'assets/[name][extname]';
				},
			},
		},
	},
});
