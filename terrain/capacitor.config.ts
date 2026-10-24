import type { CapacitorConfig } from '@capacitor/cli';

// Question 27 (2026-09-28) : le pilote passe par un serveur EN LIGNE en HTTPS. Le http
// (serveur de dev sur le réseau local) n'est permis que dans une build de dev :
// LY_TERRAIN_DEV=1 npx cap sync android
const dev = process.env.LY_TERRAIN_DEV === '1';

const config: CapacitorConfig = {
    appId: 'ci.lyagricole.terrain',
    appName: 'LY Terrain',
    webDir: 'build',
    android: { allowMixedContent: dev },
    server: dev ? { androidScheme: 'http', cleartext: true } : { androidScheme: 'https' },
};

export default config;
