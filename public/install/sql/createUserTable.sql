CREATE TABLE users
(
    screenname CHARACTER VARYING(255),
    pw         CHARACTER VARYING(255),
    email      CHARACTER VARYING(255),
    zone       CHARACTER VARYING,
    parentdb   VARCHAR(255),
    created    TIMESTAMP WITH TIME ZONE DEFAULT ('now' :: TEXT) :: TIMESTAMP(0) WITH TIME ZONE
);