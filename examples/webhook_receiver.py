"""Reference verifier for LagosPanel outgoing webhooks; Python 3 standard library.

Use behind HTTPS, read exact request bytes, and keep the key server-side.
This is NOT an HTTP server. Do not call external APIs inside apply_once expecting
exactly-once remote effects: those APIs need their own idempotency keys/outbox.
"""
import hashlib
import hmac
import json
import re
import sqlite3
import time
import uuid


def verify_request(body: bytes, signature: str, event_id: str, event_type: str,
                   secret: str, *, now: int | None = None) -> dict:
    if not isinstance(body, bytes) or len(body) > 16384:
        raise ValueError('Invalid payload size')
    if not isinstance(secret, str) or not re.fullmatch(r'[a-f0-9]{64}', secret):
        raise ValueError('Invalid signing key')
    match = re.fullmatch(r't=([0-9]{1,12}),v1=([a-f0-9]{64})', signature or '')
    if not match:
        raise ValueError('Invalid signature header')
    timestamp = int(match[1])
    if abs((int(time.time()) if now is None else now) - timestamp) > 300:
        raise ValueError('Expired or future signature')
    expected = hmac.new(secret.encode('ascii'), match[1].encode('ascii') + b'.' + body,
                        hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, match[2]):
        raise ValueError('Signature mismatch')
    try:
        data = json.loads(body)
        if not isinstance(data, dict) or type(data.get('schema_version')) is not int or data['schema_version'] != 1:
            raise ValueError('Unsupported schema')
        if data.get('id') != event_id or str(uuid.UUID(event_id)) != event_id:
            raise ValueError('Event ID mismatch')
        if data.get('type') != event_type or not isinstance(event_type, str):
            raise ValueError('Event type mismatch')
        resource = data['data']
        if not isinstance(resource, dict) or type(resource.get('id')) is not int or resource['id'] < 1:
            raise ValueError('Invalid resource ID')
        if resource.get('resource') not in ('invoice', 'order', 'service', 'ticket', 'quote', 'bulletin'):
            raise ValueError('Invalid resource type')
    except (KeyError, TypeError, AttributeError, UnicodeError, json.JSONDecodeError) as error:
        raise ValueError('Invalid event') from error
    return data


def apply_once(db: sqlite3.Connection, event: dict, handler) -> bool:
    """Deduplicate VERIFIED events and local DB effects in one SQLite transaction.

    Call verify_request first. handler(db, event) must use this same transaction
    and must not commit/rollback itself. Return 2xx only after this function
    succeeds; a duplicate is also a successful acknowledgement.
    """
    if db.in_transaction:
        raise ValueError('Use a connection without an open transaction')
    db.execute('CREATE TABLE IF NOT EXISTS lagos_received (event_id TEXT PRIMARY KEY, received_at INTEGER NOT NULL)')
    with db:
        cursor = db.execute('INSERT OR IGNORE INTO lagos_received VALUES (?, ?)',
                            (event['id'], int(time.time())))
        if not cursor.rowcount:
            return False
        handler(db, event)
    return True
