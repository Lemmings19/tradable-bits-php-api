# tradable-bits-php-api

- A [Tradable Bits](http://tradablebits.com/) API written in PHP.
- **The OAuth functionality has not been tested.**<sup>1</sup>
- This API was adapted from the [Instagram-PHP-API](https://github.com/cosenary/Instagram-PHP-API).
- If you'd like to add to this repo<sup>1</sup>, a good place to start would be adding more functions near `getStreamMedia()` and `getStatus()`. Those functions hit TBit's API, but there are a whole bunch of other endpoints that you could make functions for.
- Instagram cut off access to their API from the public so I started using Tradable Bits as a workaround (you can get to your Instagram feed via TBits).
- Tradable Bits offers the ability to consolidate multiple social media feeds into one managable feed (or 'stream'). They offer a [RESTful API](http://tradablebits.com/developers) for their service, this code interfaces with that API.
- The author of this code has no affiliation with Tradable Bits.

<sup>1</sup> As of initial commit.

Here is some sample code for using the library:
```php
include 'TradableBits.php';

/**
 * Gets Tradable Bits stream data and merges it into an image array.
 *
 * @var array    $images    An array intended to hold image data.
 * @var string   $streamKey The TBits stream key to pull from.
 *
 * @return array $images The image array with the Tradable bits data merged
 *                       into it.
 */
function getTradableBitsData($images, $streamKey = null)
{
	// How many times we will call for new media.
	// Equivalent to:
	// ($mediaLimit * TradableBits::MEDIA_LIMIT_DEFAULT) or (2 * 100)
	$mediaLimit = 2;

	if (!$streamKey) {
		$streamKey = 'your_stream_key_here';
	}

	$tradableBits = new TradableBits(array(
		'apiKey' => 'your_key_here',
		'apiSecret' => 'your_secret_here',
		'apiCallback' => 'your_api_callback_here',
		'accountId' => 'your_account_id_here',
		'streamKey' => $streamKey
		)
	);

	// Fetches the first chunk of the most recent media from the stream.
	$streamData = $tradableBits->getStreamMedia();

	// Ensure we have results to work with.
	if ($streamData['meta']['count'] > 0) {
		$images = array_merge($streamData['data'], $images);
		$lastMinTimeKey = $streamData['meta']['min_time_key'];

		// Fetches the next chunk(s) of the most recent media from the stream.
		for ($i = 0; $i < $mediaLimit; $i++) {
			$streamData = $tradableBits->getStreamMedia(null, $lastMinTimeKey);
			if ($streamData['meta']['count'] > 0) {
				$images = array_merge($streamData['data'], $images);
				$lastMinTimeKey = $streamData['meta']['min_time_key'];
			} else {
				break;
			}
		}
	}
	return $images;
}
```
