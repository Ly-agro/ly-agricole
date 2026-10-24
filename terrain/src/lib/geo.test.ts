import { describe, expect, it } from 'vitest';
import { distanceM, fermer, garderPoint, hectares, polygone, type Position, surfaceM2 } from './geo';
import { verifierAdresse } from './serveur';

/** Carré de `cote` mètres près de Korhogo, comme ParcelleFactory::geometrieCarre côté serveur. */
function carre(cote: number, lat = 9.45, lng = -5.63): Position[] {
    const dLat = ((cote / 6378137) * 180) / Math.PI;
    const dLng = dLat / Math.cos((lat * Math.PI) / 180);

    return [[lng, lat], [lng + dLng, lat], [lng + dLng, lat + dLat], [lng, lat + dLat]];
}

describe('géométrie du relevé', () => {
    it('surface d\'un carré de 100 m ≈ 1 ha, fermé ou non', () => {
        expect(surfaceM2(carre(100))).toBeGreaterThan(9_990);
        expect(surfaceM2(carre(100))).toBeLessThan(10_010);
        expect(surfaceM2(fermer(carre(100)))).toBe(surfaceM2(carre(100)));
        expect(hectares(surfaceM2(carre(100)))).toBe('1,00 ha');
    });

    it('moins de 3 sommets : surface nulle', () => {
        expect(surfaceM2(carre(100).slice(0, 2))).toBe(0);
    });

    it('le polygone GeoJSON est fermé', () => {
        const p = polygone(carre(10));
        expect(p.coordinates[0]).toHaveLength(5);
        expect(p.coordinates[0][0]).toEqual(p.coordinates[0][4]);
    });

    it('filtre : point imprécis ou trop proche ignoré', () => {
        const [a, b] = carre(100);
        expect(garderPoint([], a, 30)).toBe(false);
        expect(garderPoint([], a, 5)).toBe(true);
        expect(garderPoint([a], a, 5)).toBe(false);
        expect(Math.round(distanceM(a, b))).toBe(100);
        expect(garderPoint([a], b, 5)).toBe(true);
    });
});

describe('adresse du serveur (question 27)', () => {
    it('https partout, http seulement en local', () => {
        expect(verifierAdresse('https://gestion.ly-agricole.ci/')).toEqual({ adresse: 'https://gestion.ly-agricole.ci' });
        expect(verifierAdresse('http://localhost:8000')).toEqual({ adresse: 'http://localhost:8000' });
        expect(verifierAdresse('http://192.168.1.20:8000')).toEqual({ adresse: 'http://192.168.1.20:8000' });
        expect(verifierAdresse('http://gestion.ly-agricole.ci')).toHaveProperty('erreur');
        expect(verifierAdresse('pas une adresse')).toHaveProperty('erreur');
    });
});
