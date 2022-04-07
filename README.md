# Core PHP

BackBee CorePHP.

## 1) Image optimizer:

Install the libraries for the image optimizer:

**Fedora:**

```yaml
yum install ImageMagick -y
yum -y install libwebp-devel
```

**Ubuntu:**

```yaml
apt-get install imagemagick
apt-get install libwebp-dev
```

## 2) Sitemap:

Edit file `res/repository/Config/sitemap.yml` and edit Sitemap parameters:

```yaml
change_freq: weekly
limits: 50000
cache_ttl: 86400
excluded: [ ]
```

* **excluded**: If you like to hide url pattern in sitemap, you can add your pattern in excluded variable.

## 3) Knowledge Graph:

Edit file `res/repository/Config/knowledgeGraph.yml` and edit Knowledge Graph parameters:

```yaml
graph:
    name: BackBee
    website_name: backbee
    website_description: Backbee
    website_search: /search
    website_search_term_string: '?q='
    logo: https://www.backbee.com/resources/integration/html/mstile-70x70.png
    image: https://www.backbee.com/resources/integration/html/mstile-310x310.png
    social_profiles: [ https://twitter.com, https://www.facebook.com ]
    twitter_card: summary_large_image
google_website_verification: https://www.google.com/webmasters/verification/home?hl=en
google_search_console: https://search.google.com/search-console
mapping_schema_types:
    Article: [ home, blank, article ]
```

* **name**: Organization name
* **website_name**: Website name
* **website_description**: Website description
* **website_search**: Website potential **SearchAction** > *website_search?q={website_search_term_string}*
* **logo**: Organization logo
* **image**: Organization image
* **social_profiles**: Organization social profiles
* **twitter_card**: Metadata twitter:card (*summary* or *summary_large_image*)
* **google_website_verification**: https://search.google.com/search-console *(fixed link)*
* **google_search_console**: https://search.google.com/search-console *(fixed link)*
* **mapping_schema_types**: Mapping schema types *(See example above: SchemaArticle mapped for scientific_article and
  news)*
