const path = require('path');

/** Sibling repo: ~/Sites/humano-whatsapp-service (not humano/whatsapp-service). */
const whatsappServiceDir =
    process.env.HUMANO_WHATSAPP_SERVICE_DIR ||
    path.join(__dirname, '..', 'humano-whatsapp-service');

/**
 * PM2 — local services for Humano (queues + WhatsApp).
 *
 * One command (from project root, PM2 installed globally):
 *   pm2 start ecosystem.queue.config.cjs
 *
 * WhatsApp runs from ../humano-whatsapp-service (override: HUMANO_WHATSAPP_SERVICE_DIR).
 *
 * Stop / restart / remove all apps in this file:
 *   pm2 stop ecosystem.queue.config.cjs
 *   pm2 restart ecosystem.queue.config.cjs
 *   pm2 delete ecosystem.queue.config.cjs
 *
 * Logs:
 *   pm2 logs
 *   pm2 logs humano-queue-email
 *   pm2 logs humano-queue-default
 *   pm2 logs whatsapp-service
 *
 * After Laravel code changes:
 *   php artisan queue:restart
 *
 * Persist across reboots:
 *   pm2 save
 *   pm2 startup   (follow the printed command once)
 */
module.exports = {
    apps: [
        {
            name: 'humano-queue-email',
            cwd: __dirname,
            script: 'artisan',
            interpreter: 'php',
            args:
                'queue:work database --queue=task-communications,notifications,mailer,campaign --sleep=3 --tries=3 --timeout=120',
            autorestart: true,
            watch: false,
            max_restarts: 20,
            min_uptime: '10s',
        },
        {
            name: 'humano-queue-default',
            cwd: __dirname,
            script: 'artisan',
            interpreter: 'php',
            args: 'queue:work database --queue=default --sleep=3 --tries=3 --timeout=120',
            autorestart: true,
            watch: false,
            max_restarts: 20,
            min_uptime: '10s',
        },
        {
            name: 'whatsapp-service',
            cwd: whatsappServiceDir,
            script: 'server.js',
            interpreter: 'node',
            autorestart: true,
            watch: false,
            max_restarts: 20,
            min_uptime: '10s',
        },
    ],
};
