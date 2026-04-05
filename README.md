# Convergent Dashboard Server - FreePBX Module

A FreePBX 17 module for managing and monitoring the Convergent Dashboard Server.

## Features

- **Service Status Monitoring**: View real-time status of the Convergent Dashboard server
- **Prerequisites Check**: Automatically verifies Node.js, ts-node, and server code are installed
- **Automated Service Setup**: Creates systemd service file and sudoers configuration
- **Service Controls**: Start, stop, restart, enable, and disable the service
- **Configuration Management**: Configure all server settings from within FreePBX
- **Automatic Credential Detection**: Automatically reads ARI, AMI, and GraphQL credentials from FreePBX/Asterisk
- **Manual Credential Override**: Optionally override auto-detected credentials with manual values
- **Log Viewing**: View service logs directly from the FreePBX interface

## Installation

```bash
git clone https://github.com/MatthewLJensen/convergent-connector \
    /var/www/html/admin/modules/convergentdashboard
chown -R asterisk:asterisk /var/www/html/admin/modules/convergentdashboard
fwconsole ma install convergentdashboard
fwconsole reload
```

## Upgrading

```bash
cd /var/www/html/admin/modules/convergentdashboard
git pull origin main
fwconsole reload
```

## Configuration

### Prerequisites

The module will check for the following prerequisites in the **Status** tab:

1. **Server Code**: The Convergent Dashboard server must be installed at `/opt/convergent-server`
2. **Node.js**: Node.js runtime (install with `dnf install nodejs`)
3. **ts-node**: TypeScript execution engine (install with `npm install -g ts-node typescript`)

### Automated Setup

Once prerequisites are installed, click the **"Setup Service"** button to:
- Create the systemd service file at `/etc/systemd/system/convergent-monitoring.service`
- Create sudoers configuration at `/etc/sudoers.d/convergent`
- Reload the systemd daemon

If the web server doesn't have sudo access, the module will provide copy/paste commands to run as root.

### Settings

Navigate to **Admin > Convergent Dashboard** in FreePBX:

#### Core Settings
- **API Key**: Your Convergent Dashboard API key for authentication
- **WebSocket Port**: The port for client WebSocket connections (default: `18443`)
- **Audio Port Range**: Internal port range for Asterisk audio streaming (default: `9000-10000`)
- **Speech Language**: BCP-47 language code for transcription (`en-US`, `fr-CA`, etc.)

#### AMI Credentials (auto-detected from manager.conf)
- **AMI Host**: Asterisk Manager Interface host
- **AMI Port**: Asterisk Manager Interface port
- **AMI Username**: Manager username
- **AMI Password**: Manager password

#### ARI Credentials (auto-detected from ari.conf and http.conf)
- **ARI URL**: Asterisk REST Interface URL
- **ARI Username**: ARI username
- **ARI Password**: ARI password

#### GraphQL Credentials (auto-detected from FreePBX database)
- **GraphQL URL**: FreePBX GraphQL API endpoint
- **Token URL**: FreePBX OAuth token endpoint
- **Client ID**: API application client ID
- **Client Secret**: API application client secret

## Manual Service Setup (Alternative)

If automated setup doesn't work, create the files manually:

### Systemd Service

Create `/etc/systemd/system/convergent-monitoring.service`:

```ini
[Unit]
Description=Convergent Dashboard Server
After=network.target asterisk.service
Wants=asterisk.service

[Service]
Type=simple
User=asterisk
Group=asterisk
WorkingDirectory=/opt/convergent-server
ExecStart=/usr/bin/ts-node --esm /opt/convergent-server/bin/convergent-monitoring.ts
Restart=on-failure
RestartSec=10
StandardOutput=journal
StandardError=journal
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

### Sudoers Configuration

Create `/etc/sudoers.d/convergent`:

```
asterisk ALL=(ALL) NOPASSWD: /bin/systemctl start convergent-monitoring
asterisk ALL=(ALL) NOPASSWD: /bin/systemctl stop convergent-monitoring
asterisk ALL=(ALL) NOPASSWD: /bin/systemctl restart convergent-monitoring
asterisk ALL=(ALL) NOPASSWD: /bin/systemctl enable convergent-monitoring
asterisk ALL=(ALL) NOPASSWD: /bin/systemctl disable convergent-monitoring
asterisk ALL=(ALL) NOPASSWD: /bin/systemctl daemon-reload
```

Then reload and start:

```bash
chmod 644 /etc/systemd/system/convergent-monitoring.service
chmod 440 /etc/sudoers.d/convergent
systemctl daemon-reload
systemctl enable convergent-monitoring
systemctl start convergent-monitoring
```

## Troubleshooting

### Service won't start
1. Check the logs: Go to **Logs** tab and view journal output
2. Verify Node.js and ts-node are installed and in PATH
3. Check the server code exists at `/opt/convergent-server`

### Credentials not detected
1. Verify ARI is configured in `/etc/asterisk/ari.conf`
2. Verify AMI is configured in `/etc/asterisk/manager.conf`
3. For GraphQL, create an API application named "convergent" in FreePBX Admin > API

### Permission errors
1. Ensure the module has correct ownership: `chown -R asterisk:asterisk /var/www/html/admin/modules/convergentdashboard`
2. Verify sudoers configuration exists at `/etc/sudoers.d/convergent`

### Setup button doesn't work
If the "Setup Service" button fails, run the provided commands manually as root. The module will display the exact commands needed.

## Requirements

- FreePBX 17.0+
- Node.js 16+
- ts-node and typescript
- Asterisk with ARI and AMI enabled

## Support

For issues and feature requests, contact Convergent Communications.
