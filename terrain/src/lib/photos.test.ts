import { afterEach, describe, expect, it, vi } from 'vitest';
import { positionActuelle } from './photos';

describe('position GPS d\'une photo', () => {
    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('une demande d\'autorisation restée sans réponse ne bloque pas : null après le délai', async () => {
        vi.useFakeTimers();
        // getCurrentPosition qui ne rappelle jamais (question « Autoriser la position ? » ignorée).
        vi.stubGlobal('navigator', { geolocation: { getCurrentPosition: () => undefined } });

        const promesse = positionActuelle(1_000);
        await vi.advanceTimersByTimeAsync(3_000);

        await expect(promesse).resolves.toBeNull();
    });

    it('une position reçue à temps est rendue', async () => {
        vi.stubGlobal('navigator', { geolocation: { getCurrentPosition: (ok: PositionCallback) =>
            ok({ coords: { latitude: 9.45, longitude: -5.63, accuracy: 8 } } as GeolocationPosition) } });

        await expect(positionActuelle()).resolves.toEqual({ lat: 9.45, lng: -5.63, precision: 8 });
    });
});
