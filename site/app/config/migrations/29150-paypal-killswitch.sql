-- Apparently production doesn't have api_disabled in the table.

INSERT IGNORE INTO config VALUES
  ('paypal_disabled', 0),
  ('api_disabled', 0);
