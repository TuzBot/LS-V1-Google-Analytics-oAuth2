alter table cms_stats_settings add column ga_client_id varchar(250) default null;
alter table cms_stats_settings add column ga_email_address varchar(250) default null;
alter table cms_stats_settings add column ga_client_secret varchar(250) default null;
alter table cms_stats_settings add column ga_redirect_url varchar(250) default null;
alter table cms_stats_settings add column ga_javascript_origin varchar(250) default null;