import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'ci.lyagricole.terrain',
    appName: 'LY Terrain',
    webDir: 'build',
    // Pilote : serveur de dev en http:// sur le réseau local. En production (sem. 12),
    // HTTPS seulement : retirer `cleartext` et `allowMixedContent`.
    android: { allowMixedContent: true },
    server: { androidScheme: 'http', cleartext: true },
};

export default config;
