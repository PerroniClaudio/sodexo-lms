# Handling Mux Webhooks in Laravel

## Purpose

Receive events from Mux about asset lifecycle changes

(e.g. asset.created, asset.ready, asset.errored).

## Why Webhooks Are Mandatory

- Asset processing is asynchronous
- Playback IDs are usable only after processing
- Direct Upload flow depends on webhooks

## Recommended Laravel Setup

- Dedicated webhook endpoint (e.g. /api/webhooks/mux)
- Signature verification enabled
- Idempotent handlers

## Minimum Events to Handle

- video.asset.ready
- video.asset.errored
- video.upload.asset_created

## Data to Store

- asset_id
- playback_id
- asset status (ready / errored)

## Security

- Always verify webhook signatures
- Never trust unauthenticated payloads
- Reject invalid signatures with 401

## Notes

- Webhook failure can break the upload pipeline
- Log all incoming events for debugging
- Treat webhooks as source of truth

``
