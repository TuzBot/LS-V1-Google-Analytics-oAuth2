alter table cms_stats_settings add column ga_refresh_token varchar(250) default null;
alter table cms_stats_settings add column ga_access_token varchar(250) default null;
alter table cms_stats_settings add column ga_access_expires datetime default null;