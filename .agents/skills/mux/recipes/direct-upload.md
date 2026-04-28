# Direct Upload with Mux (Laravel)

## Purpose

Allow users to upload videos directly from the browser to Mux,

without passing large files through the Laravel backend.

## Why Direct Uploads

- Reduces server load
- Faster uploads
- Better scalability
- Officially recommended by Mux

## Flow Overview

1. Backend creates a Direct Upload URL using Mux API
2. Frontend uploads the file directly to Mux
3. Mux processes the asset
4. Mux sends a webhook when the asset is ready

## Backend (Laravel) Responsibilities

- Authenticate with Mux API
- Create a Direct Upload
- Store upload ID (optional)
- Handle webhook events (asset.ready)

## Required Mux Features

- Video API enabled
- Access Token (ID + Secret)
- Webhooks configured

## Notes

- Direct Upload does NOT immediately create an asset
- Always rely on webhooks to know when processing is complete
- Do not assume playback availability until webhook confirmation

``
