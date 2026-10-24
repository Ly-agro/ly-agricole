import adapter from '@sveltejs/adapter-static';

/** Appli statique (D2) : tout tourne sur le téléphone, le serveur n'est qu'une API. */
export default {
    kit: {
        adapter: adapter({ fallback: 'index.html' }),
    },
};
