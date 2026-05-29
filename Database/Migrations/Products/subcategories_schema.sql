--
-- PostgreSQL database dump
--

\restrict FEr1NMiRATdjXx6eiNIlcdl3cbI8g6P83iIVvPMjIvppKvgGihGPQhpxaJJcMgN

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

SET default_tablespace = '';

SET default_table_access_method = heap;

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
-- Name: idx_subcategories_user; Type: INDEX; Schema: public; Owner: admin
--

CREATE INDEX idx_subcategories_user ON public.subcategories USING btree (user_id);


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
-- Name: subcategories; Type: ROW SECURITY; Schema: public; Owner: admin
--

ALTER TABLE public.subcategories ENABLE ROW LEVEL SECURITY;

--
-- Name: subcategories user_isolation; Type: POLICY; Schema: public; Owner: admin
--

CREATE POLICY user_isolation ON public.subcategories USING ((user_id = (current_setting('app.current_user_id'::text, true))::uuid));


--
-- PostgreSQL database dump complete
--

\unrestrict FEr1NMiRATdjXx6eiNIlcdl3cbI8g6P83iIVvPMjIvppKvgGihGPQhpxaJJcMgN

