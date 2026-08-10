-- `product_returns` was scaffolded in the original Module 1 schema but
-- never wired to any application code. Module 25 (return_requests /
-- return_request_items / return_request_status_history) is the real
-- returns/refunds implementation, so this dead table is dropped to
-- avoid two competing "returns" concepts in the schema.
DROP TABLE IF EXISTS `product_returns`;
