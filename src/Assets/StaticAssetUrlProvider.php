<?php


namespace Ingenerator\PHPUtils\Assets;


use InvalidArgumentException;
use RuntimeException;

/**
 * Simple / basic provider for CSS, JS, image etc asset URLs supporting cache-busting in dev and remote hosting in prod
 *
 * This class provides a very simple mechanism for generating URLs to a site's inbuilt CSS/JS/etc. It operates in two
 * modes.
 *
 * In `local` (developer workstation) mode, it assumes asset files are reachable on the same web host as the main site,
 * with the files located below the webserver's document root. Assets are served with a `?v=...` querystring based on
 * the file modification time, providing automatic cachebusting during development. Assets that don't exist will throw
 * an exception to help identify typos / incorrect relative urls during development.
 *
 * In `remote` (CI / qa / production) mode, it assumes asset files have been uploaded to a remote file hosting service
 * (google cloud / s3 / etc) during the build process. The application prefixes all asset URLs with a host/path prefix
 * pointing to that service, or to a CDN or similar. Usually this path prefix - set at build time - will contain an SHA
 * or similar identifier so that the assets are in sync with the application.
 *
 * A build script should therefore:
 *
 *   * Compile the assets as required
 *   * Choose a suitable remote hosting path for this specific version of the assets
 *   * Upload them to the remote hosting in the versioned path
 *   * Create a config file for the application containing the URL prefix - e.g.
 *     `<?php return 'http://my.cool.cdn/project/version-a923';`
 *     this file should then be deployed alongside the application.
 *
 * All this class does is read that file to get the URL prefix, and concat it onto the front of every asset path.
 *
 */
class StaticAssetUrlProvider
{
    const MODE_LOCAL  = 'local';
    const MODE_REMOTE = 'remote';

    /**
     * @var string
     */
    protected $local_asset_path;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var string
     */
    protected $remote_asset_url;

    /**
     * @param string $mode                local or remote mode (see above) - commonly toggle based on runtime environment
     * @param string $local_asset_path    base path on disk that all assets are relative to (in development)
     * @param string $asset_base_url_file path to a php file generated at build time that returns the URL host/path prefix for remote mode
     */
    public function __construct(
        string $mode,
        string $local_asset_path,
        string $asset_base_url_file
    ) {
        if ( ! in_array($mode, [static::MODE_LOCAL, static::MODE_REMOTE])) {
            throw new InvalidArgumentException('Invalid asset mode `'.$mode.'` for '.__CLASS__);
        }
        $this->mode             = $mode;
        $this->local_asset_path = rtrim($local_asset_path, '/');

        if ($this->mode === static::MODE_REMOTE) {
            $this->remote_asset_url = $this->loadAssetBaseUrl($asset_base_url_file);
        }
    }

    protected function loadAssetBaseUrl(string $asset_base_url_file): string
    {
        if ( ! file_exists($asset_base_url_file)) {
            throw new InvalidArgumentException('No asset base url file at '.$asset_base_url_file);
        }

        $url = require $asset_base_url_file;

        if (empty($url) OR ! is_string($url)) {
            throw new RuntimeException('Invalid content in asset base url file '.$asset_base_url_file);
        }

        return $url;
    }

    /**
     * Generates a URL for a static asset, given its relative path.
     *
     * @param string $rel_path the path of the asset within the docroot / uploaded asset files
     *
     * @return string the full URL to render to the client
     */
    public function getUrl(string $rel_path): string
    {
        if ($rel_path[0] !== '/') {
            $rel_path = '/'.$rel_path;
        }

        if ($this->mode === static::MODE_LOCAL) {
            return $this->getLocalTimestampedUrl($rel_path);
        } else {
            return $this->remote_asset_url.$rel_path;
        }
    }

    /**
     * @param string $rel_path
     *
     * @return string
     */
    protected function getLocalTimestampedUrl(string $rel_path): string
    {
        $local_path = $this->local_asset_path.$rel_path;
        if ( ! file_exists($local_path)) {
            throw new RuntimeException('Undefined asset file '.$local_path);
        }

        return $rel_path.'?v='.filemtime($local_path);
    }
}
