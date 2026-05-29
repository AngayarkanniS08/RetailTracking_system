--
-- PostgreSQL database dump
--

\restrict ujaVoQgpcZvpyCRrWk3N3H2bcOVUb631nu78Eb641xbVuSPuEqDWpDHpEhcKYDu

-- Dumped from database version 17.10 (Ubuntu 17.10-0ubuntu0.25.10.1)
-- Dumped by pg_dump version 17.10 (Ubuntu 17.10-0ubuntu0.25.10.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: categories; Type: TABLE; Schema: public; Owner: admin
--

CREATE TABLE public.categories (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    name text NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    user_id uuid NOT NULL
);

ALTER TABLE ONLY public.categories FORCE ROW LEVEL SECURITY;


ALTER TABLE public.categories OWNER TO admin;

--
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    filename text,
    executed_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_resets; Type: TABLE; Schema: public; Owner: admin
--

CREATE TABLE public.password_resets (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    token text NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    created_at timestamp with time zone DEFAULT now()
);


ALTER TABLE public.password_resets OWNER TO admin;

--
-- Name: products; Type: TABLE; Schema: public; Owner: admin
--

CREATE TABLE public.products (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    name character varying(255) NOT NULL,
    category_id uuid NOT NULL,
    unit character varying(50) NOT NULL,
    hsn_code character varying(20),
    gst_rate numeric(5,2) DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    subcategory_id uuid,
    user_id uuid NOT NULL
);

ALTER TABLE ONLY public.products FORCE ROW LEVEL SECURITY;


ALTER TABLE public.products OWNER TO admin;

--
-- Name: subcategories; Type: TABLE; Schema: public; Owner: admin
--

CREATE TABLE public.subcategories (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    category_id uuid NOT NULL,
    name text NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    user_id uuid NOT NULL
);

ALTER TABLE ONLY public.subcategories FORCE ROW LEVEL SECURITY;


ALTER TABLE public.subcategories OWNER TO admin;

--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    username text NOT NULL,
    password_hash text NOT NULL,
    full_name text,
    email text DEFAULT 'test@gmail.com'::text NOT NULL,
    created_at timestamp with time zone DEFAULT now()
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Data for Name: categories; Type: TABLE DATA; Schema: public; Owner: admin
--

COPY public.categories (id, name, created_at, user_id) FROM stdin;
e5007a43-c799-4dfc-94e4-af45da080da6	Textiles	2026-05-23 12:10:06.276901+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
af425337-7432-477a-8698-7516d1a58e02	Thread Work	2026-05-23 12:10:06.276901+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
c1895a67-0540-410a-8adf-dba74fe7fe20	Beads & Buttons	2026-05-23 12:10:06.276901+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
9e7eb892-50d5-40bd-98d8-50529f8c5955	New Category Test	2026-05-23 12:19:59.363873+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
60acb772-3246-419b-9412-05434f26f518	__debug_test_1779807885316	2026-05-26 20:34:45.352616+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
890c29ca-c1a8-45bd-96bf-9ea79472a28d	lining	2026-05-26 20:35:47.891005+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
ac5072ef-e825-4ca1-9dc3-6a5e5fe2e972	embroidary	2026-05-26 20:36:14.995844+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
bf04f238-fbda-4590-a1a0-f2023745f237	fabric	2026-05-26 20:40:01.65811+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
98fffd8c-2700-432a-9638-42a0d011dd78	zip	2026-05-26 21:57:08.30956+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
4b6cfc73-a99e-42b0-aba0-8462b6f9f5bc	lace	2026-05-26 22:22:08.260638+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
3db05071-7592-4393-9230-e340b299bc2f	Beads	2026-05-27 12:13:24.485186+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
80e052ee-8f64-4c08-9234-3127e77a0b95	aari work	2026-05-27 12:59:58.03686+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
f3df0454-dd80-49e4-8426-8a8a67b3716f	Test Category	2026-05-27 13:04:37.349017+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
26f8d58c-b330-41ad-bab7-71e9b1f6ad8b	Machines	2026-05-27 13:15:11.364315+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
8ca97fcd-e29e-4c43-9041-57b11d63b330	Sewing	2026-05-27 13:16:13.318025+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
26016e77-0195-4ec5-82ae-106cb7d11fb4	stones	2026-05-27 13:29:18.503032+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
e7beb8c8-17f3-49a6-a43e-f01920cea50d	RLS_Test_Category	2026-05-27 20:41:51.099097+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
2e3dd58e-71da-4b1a-bb4c-cb35eaa31bcc	New Test Category 1	2026-05-27 22:34:51.599368+05:30	181cca7c-4d7e-4a38-9212-90dd5964a282
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.migrations (id, filename, executed_at) FROM stdin;
\.


--
-- Data for Name: password_resets; Type: TABLE DATA; Schema: public; Owner: admin
--

COPY public.password_resets (id, user_id, token, expires_at, created_at) FROM stdin;
\.


--
-- Data for Name: products; Type: TABLE DATA; Schema: public; Owner: admin
--

COPY public.products (id, name, category_id, unit, hsn_code, gst_rate, created_at, subcategory_id, user_id) FROM stdin;
c474dbd2-d917-45f4-9b33-cdbed63d6e55	blouse	e5007a43-c799-4dfc-94e4-af45da080da6	set	\N	0.00	2026-05-27 19:05:05.712265+05:30	\N	0c0adcb3-69da-41ee-a1f6-60e25629338e
f32d9c9c-1699-4049-9008-1b89f2ba5912	orange 01	890c29ca-c1a8-45bd-96bf-9ea79472a28d	mtr	\N	0.00	2026-05-27 19:47:55.646126+05:30	0bed26b5-b2e4-4f49-bb48-75f3b50e0567	0c0adcb3-69da-41ee-a1f6-60e25629338e
9d9bd02b-78ba-43d4-bd6d-81799d6ec99e	orange 02	890c29ca-c1a8-45bd-96bf-9ea79472a28d	mtr	\N	0.00	2026-05-27 19:48:07.740046+05:30	0bed26b5-b2e4-4f49-bb48-75f3b50e0567	0c0adcb3-69da-41ee-a1f6-60e25629338e
dcf65e14-314c-447f-bd10-6dbb1d60cab9	orange 03	890c29ca-c1a8-45bd-96bf-9ea79472a28d	mtr	\N	0.00	2026-05-27 19:48:28.25677+05:30	0bed26b5-b2e4-4f49-bb48-75f3b50e0567	0c0adcb3-69da-41ee-a1f6-60e25629338e
d99937c6-39ae-4dac-a582-5efa42b581e0	orange 04	890c29ca-c1a8-45bd-96bf-9ea79472a28d	mtr	\N	0.00	2026-05-27 19:48:45.624582+05:30	0bed26b5-b2e4-4f49-bb48-75f3b50e0567	0c0adcb3-69da-41ee-a1f6-60e25629338e
c333be1b-d426-4428-9f10-ed7dcc8baf51	orange 05	890c29ca-c1a8-45bd-96bf-9ea79472a28d	mtr	\N	0.00	2026-05-27 19:49:02.166307+05:30	0bed26b5-b2e4-4f49-bb48-75f3b50e0567	0c0adcb3-69da-41ee-a1f6-60e25629338e
a7d31294-d0fc-459b-85e4-3ab72f80ce3e	orange 06	890c29ca-c1a8-45bd-96bf-9ea79472a28d	mtr	\N	0.00	2026-05-27 19:49:22.548485+05:30	0bed26b5-b2e4-4f49-bb48-75f3b50e0567	0c0adcb3-69da-41ee-a1f6-60e25629338e
7ef04c43-c307-4c8d-96b9-0618e6aa2f95	orange 07	890c29ca-c1a8-45bd-96bf-9ea79472a28d	mtr	\N	0.00	2026-05-27 19:49:37.26423+05:30	0bed26b5-b2e4-4f49-bb48-75f3b50e0567	0c0adcb3-69da-41ee-a1f6-60e25629338e
\.


--
-- Data for Name: subcategories; Type: TABLE DATA; Schema: public; Owner: admin
--

COPY public.subcategories (id, category_id, name, created_at, user_id) FROM stdin;
4ecc0121-9cb9-429a-a465-9ddf4a1affd8	80e052ee-8f64-4c08-9234-3127e77a0b95	FixTest_1779868334	2026-05-27 13:22:14.420458+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
0bed26b5-b2e4-4f49-bb48-75f3b50e0567	890c29ca-c1a8-45bd-96bf-9ea79472a28d	Orange color	2026-05-27 13:24:14.415099+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
ba44c233-88de-40e5-8c56-d787bd146508	26016e77-0195-4ec5-82ae-106cb7d11fb4	golden stones	2026-05-27 13:29:32.95142+05:30	0c0adcb3-69da-41ee-a1f6-60e25629338e
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, username, password_hash, full_name, email, created_at) FROM stdin;
e165e33e-0b13-4db9-93bb-79858a78a74a	testuser123	$2y$12$8HNxsIOVS9shtEwToydUae7Gl59PbvcqDJcSmOOZgjEUToepBzpFq	\N	testuser123@example.com	2026-05-24 02:26:33.759745+05:30
2bbb4da4-000d-4d26-84f8-34dcd2dc9092	surya	$2y$12$UjmdjKN8x3ateo.xbvXH5O81qvYPlLWyDyBctLTyio4bavMbNDTn6	\N	surya@gmail.com	2026-05-24 12:54:18.81464+05:30
af01286b-dee7-4713-8898-70856d898e3c	angayarkanni	$2y$12$fuZTKCYLWbvDGq6l/4a5qe8MDqsugERTRr1BCtZheVkafpNzDA8f2	\N	angayarkanni834@gmail.com	2026-05-24 02:27:37.046752+05:30
9b742535-d021-4019-8191-78f949c0702b	Aishwarya	$2y$12$KGDX9G2zor8DTUPnyYAyHuyA0kccBx7GGSh9bGOiX/UXt6MqaT8Jm	\N	aish@gmail.com	2026-05-27 20:08:14.50524+05:30
181cca7c-4d7e-4a38-9212-90dd5964a282	testuser1	$2y$12$IeQsuYQE61DLrqoqnuxVheYP.nPd8UMF2CvMUw0m71TQFKlNc4sW2	\N	testuser1@example.com	2026-05-27 22:33:01.376504+05:30
0c0adcb3-69da-41ee-a1f6-60e25629338e	admin	$2y$12$NB.ILdzRPn4Er2LzedmwteHM6aQ6fDNp/MDDif69NjE7c1mpFAJwO	\N	test@gmail.com	2026-05-24 02:26:33.759745+05:30
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 1, false);


--
-- Name: categories categories_name_key; Type: CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_name_key UNIQUE (name);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_filename_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_filename_key UNIQUE (filename);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_resets password_resets_pkey; Type: CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_pkey PRIMARY KEY (id);


--
-- Name: password_resets password_resets_token_key; Type: CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_token_key UNIQUE (token);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: subcategories subcategories_category_id_name_key; Type: CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.subcategories
    ADD CONSTRAINT subcategories_category_id_name_key UNIQUE (category_id, name);


--
-- Name: subcategories subcategories_pkey; Type: CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.subcategories
    ADD CONSTRAINT subcategories_pkey PRIMARY KEY (id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- Name: idx_categories_user; Type: INDEX; Schema: public; Owner: admin
--

CREATE INDEX idx_categories_user ON public.categories USING btree (user_id);


--
-- Name: idx_products_category; Type: INDEX; Schema: public; Owner: admin
--

CREATE INDEX idx_products_category ON public.products USING btree (category_id);


--
-- Name: idx_products_user; Type: INDEX; Schema: public; Owner: admin
--

CREATE INDEX idx_products_user ON public.products USING btree (user_id);


--
-- Name: idx_subcategories_user; Type: INDEX; Schema: public; Owner: admin
--

CREATE INDEX idx_subcategories_user ON public.subcategories USING btree (user_id);


--
-- Name: categories categories_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: password_resets password_resets_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: products products_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE RESTRICT;


--
-- Name: products products_subcategory_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_subcategory_id_fkey FOREIGN KEY (subcategory_id) REFERENCES public.subcategories(id) ON DELETE SET NULL;


--
-- Name: products products_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: subcategories subcategories_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.subcategories
    ADD CONSTRAINT subcategories_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: subcategories subcategories_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: admin
--

ALTER TABLE ONLY public.subcategories
    ADD CONSTRAINT subcategories_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: categories; Type: ROW SECURITY; Schema: public; Owner: admin
--

ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;

--
-- Name: products; Type: ROW SECURITY; Schema: public; Owner: admin
--

ALTER TABLE public.products ENABLE ROW LEVEL SECURITY;

--
-- Name: subcategories; Type: ROW SECURITY; Schema: public; Owner: admin
--

ALTER TABLE public.subcategories ENABLE ROW LEVEL SECURITY;

--
-- Name: categories user_isolation; Type: POLICY; Schema: public; Owner: admin
--

CREATE POLICY user_isolation ON public.categories USING ((user_id = (current_setting('app.current_user_id'::text, true))::uuid));


--
-- Name: products user_isolation; Type: POLICY; Schema: public; Owner: admin
--

CREATE POLICY user_isolation ON public.products USING ((user_id = (current_setting('app.current_user_id'::text, true))::uuid));


--
-- Name: subcategories user_isolation; Type: POLICY; Schema: public; Owner: admin
--

CREATE POLICY user_isolation ON public.subcategories USING ((user_id = (current_setting('app.current_user_id'::text, true))::uuid));


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: pg_database_owner
--

GRANT CREATE ON SCHEMA public TO admin;


--
-- Name: TABLE users; Type: ACL; Schema: public; Owner: postgres
--

GRANT ALL ON TABLE public.users TO admin;


--
-- PostgreSQL database dump complete
--

\unrestrict ujaVoQgpcZvpyCRrWk3N3H2bcOVUb631nu78Eb641xbVuSPuEqDWpDHpEhcKYDu

