# Core PHP

BackBee Cloud CorePHP.

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
sitemap:
    index:
        active: true
        url_pattern: /sitemap.xml
        decorator: BackBeePlanet\Sitemap\Decorator\SitemapIndex
        collector: BackBeePlanet\Sitemap\Query\IndexCollector
        cache-control:
            max_age: 86400
    urlset:
        active: true
        url_pattern: /sitemap-{index}.xml
        decorator: BackBeePlanet\Sitemap\Decorator\UrlSet
        iterator-step: 1500   # memory limit consideration
        limits:
            num_loc_per_page: 50000
        cache-control:
            max_age: 86400
    excluded: [ ]
```

* **excluded**: If you like to hide url pattern in sitemap, you can add your pattern in excluded variable. 