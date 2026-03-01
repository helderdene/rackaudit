# Deployment Configuration

This directory contains deployment-related configuration files.

## Laravel Reverb WebSocket Server

Laravel Reverb provides real-time WebSocket functionality for broadcasting events to connected clients.

### Local Development

Start the Reverb server locally:

```bash
php artisan reverb:start
```

Or with explicit host and port:

```bash
php artisan reverb:start --host=127.0.0.1 --port=8080
```

### Production Deployment with Supervisor

1. Install Supervisor on your server:

```bash
# Debian/Ubuntu
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

2. Copy the configuration file:

```bash
sudo cp deployment/reverb-worker.conf /etc/supervisor/conf.d/rackaudit-reverb.conf
```

3. Update the configuration file with your actual paths:
   - Update `command` path to your application directory
   - Update `user` to your web server user (www-data, nginx, etc.)
   - Update `stdout_logfile` path as needed

4. Create the log directory:

```bash
sudo mkdir -p /var/log/rackaudit
sudo chown www-data:www-data /var/log/rackaudit
```

5. Reload and start Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rackaudit-reverb
```

6. Verify the process is running:

```bash
sudo supervisorctl status rackaudit-reverb
```

### Environment Variables

Ensure these variables are set in your `.env` file:

```env
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=your-domain.com
REVERB_PORT=8080
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### TLS/SSL Configuration

For production, use a reverse proxy (Nginx or Caddy) to handle SSL termination:

**Nginx Example:**

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 86400;
}
```

### Troubleshooting

**Check Reverb logs:**
```bash
tail -f /var/log/rackaudit/reverb.log
```

**Restart the Reverb server:**
```bash
sudo supervisorctl restart rackaudit-reverb
```

**Test WebSocket connection:**
Use browser developer tools to check WebSocket connections under the Network tab.
