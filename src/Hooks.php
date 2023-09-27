<?php
// Copyright 2021 Fandom, Inc.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
// http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

namespace MessageCachePerformance;

use Config;
use LocalisationCache;
use MediaWiki\Cache\Hook\MessageCache__getHook;
use MediaWiki\Hook\AfterFinalPageOutputHook;
use MediaWiki\MainConfigNames;
use OutputPage;

class Hooks implements MessageCache__getHook, AfterFinalPageOutputHook {
	/**
	 * Map of known message keys defined by core/extensions (key => true)
	 * @var bool[]|null $knownMsgKeys
	 */
	private ?array $knownMsgKeys = null;

	/**
	 * List of messages that where skipped from checking in the cache.
	 * Used to display debug info in ParserOutput.
	 * @see Hooks::onParserOutputPostCacheTransform()
	 * @var array
	 */
	private array $skippedMessages = [];

	private Config $config;

	private LocalisationCache $localisationCache;

	private MessageCachePerformanceMatcher $stringMatcher;

	public function __construct(
		Config $config,
		LocalisationCache $localisationCache,
		MessageCachePerformanceMatcher $stringMatcher
	) {
		$this->config = $config;
		$this->localisationCache = $localisationCache;
		$this->stringMatcher = $stringMatcher;
	}

	/**
	 * Hook: MessageCache::get
	 * Due to https://phabricator.wikimedia.org/T193271, lookups for messages that are not defined in core/extensions
	 * and are not customized on the wiki trigger memcached/APCu lookups. This can be quite expensive when many wikis
	 * and messages are involved.
	 *
	 * This hook catches the most common messages being looked up that are known not to exist, and short-circuits the
	 * MessageCache lookup by explicitly designating them as nonexistent.
	 *
	 * See: https://phabricator.wikimedia.org/T193271
	 * See also: https://phabricator.wikimedia.org/T275033
	 *
	 * @param string &$key message key being looked up
	 * @see MessageCache::get()
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onMessageCache__get( &$key ) {
		$knownMsgKeys = $this->getKnownMsgKeys();

		// Message is known to exist in code - nothing to do.
		if ( isset( $knownMsgKeys[$key] ) ) {
			return true;
		}

		// The message is known to not exist in code and cannot be customized
		if ( $this->stringMatcher->searchIn( $key ) ) {
			$this->skippedMessages[$key] = 1;
			return false;
		}

		return true;
	}

	/**
	 * Adds debug info to ParserOutput with skipped variables
	 *
	 * @param OutputPage $output
	 * @return void This hook must not abort, it must return no value
	 */
	public function onAfterFinalPageOutput( $output ): void {
		// do not render debug information for api requests
		if ( !defined( 'MEDIAWIKI' ) || defined( 'MW_API' ) ) {
			return;
		}

		$enableDebug = $this->config->get( 'MessageCachePerformanceEnableDebug' );
		if ( !$enableDebug ) {
			return;
		}

		if ( count( $this->skippedMessages ) > 0 ) {
			$keys = implode( ', ', array_keys( $this->skippedMessages ) );
			echo "\n<!-- MessageCachePerformance skipped messages: $keys -->\n";
		}
	}

	/**
	 * Preload all known message keys defined by core/extensions
	 * @return bool[] map of message key => true
	 */
	private function getKnownMsgKeys(): array {
		if ( !$this->knownMsgKeys ) {
			$this->knownMsgKeys = array_flip(
				$this->localisationCache->getSubitemList(
					$this->config->get( MainConfigNames::LanguageCode ),
					'messages'
				)
			);
		}

		return $this->knownMsgKeys;
	}
}
