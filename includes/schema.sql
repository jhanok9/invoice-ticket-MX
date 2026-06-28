CREATE TABLE IF NOT EXISTS companies (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    rfc        VARCHAR(20)  NOT NULL,
    email      VARCHAR(255),
    phone      VARCHAR(30),
    zip_code   VARCHAR(10),
    tax_regime VARCHAR(100),
    notes      TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS tickets (
    id            SERIAL PRIMARY KEY,
    company_id    INTEGER REFERENCES companies(id) ON DELETE SET NULL,
    image_path    VARCHAR(500),
    store_name    VARCHAR(255),
    store_rfc     VARCHAR(20),
    ticket_number VARCHAR(100),
    serie         VARCHAR(50),
    purchase_date DATE,
    subtotal      NUMERIC(12,2),
    tax           NUMERIC(12,2),
    total         NUMERIC(12,2),
    status        VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','stamped','failed')),
    stamped_at    TIMESTAMPTZ,
    raw_ai_json   JSONB,
    notes         TEXT,
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    updated_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS stamp_logs (
    id           SERIAL PRIMARY KEY,
    ticket_id    INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    attempted_at TIMESTAMPTZ DEFAULT NOW(),
    result       VARCHAR(20) CHECK (result IN ('success','error')),
    message      TEXT,
    screenshot   VARCHAR(500)
);

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tickets_updated_at ON tickets;
CREATE TRIGGER tickets_updated_at
    BEFORE UPDATE ON tickets
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
