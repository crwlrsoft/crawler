<!doctype html>
<html lang="en">
<head>
    <meta charset=utf-8>
    <title>Prevent Homograph Attacks using the crwlr/url Package - crwlr.software</title>
</head>
<body id="crw">
<nav>
    <div class="inner">
        <a href="https://www.crwlr.software" class="logo" title="crwlr.software"></a>
        <ul>
            <li class="sub-nav-parent"><a href="https://www.crwlr.software/packages" title="Overview of PHP packages">Packages</a></li>
            <li><a href="https://www.crwlr.software/blog" title="Blog about crawling and scraping with PHP">Blog</a></li>
            <li><a href="https://www.crwlr.software/contact" title="Get in touch">Contact</a></li>
        </ul>
    </div>
</nav>
<main id="content">
    <div class="inner">
        <article class="blog-post">
            <h1>Prevent Homograph Attacks using the crwlr/url Package</h1>
            <div class="date">2022-01-19</div>
            <p>This post is not crawling/scraping related, but about another
                valuable use case for the url package, to prevent so-called
                homograph attacks.</p>
            <h2>About the attack</h2>
            <p>Homograph attacks are using internationalized domain names (IDN) for
                malicious links including domains that look like trusted organizations.
                You might know attacks where they want to trick you with typos
                like faecbook or things like zeros instead of Os (g00gle).
                Using internationalized domain names this kind of attack is even
                harder to spot because they are using characters that almost exactly
                look like other characters (also depending on the font they're
                displayed with).</p>
            <h3>Can you see the difference between those two As?</h3>
            <p>a а</p>
            <p>No? But in fact they aren't the same. The second one is a Cyrillic
                character.<br />
                You can check it e.g. by using PHP's ord function.</p>
            <pre><code class="language-php">var_dump(ord('a')); // int(97)
var_dump(ord('а')); // int(208)</code></pre>
            <p>Browsers already implemented mechanisms to warn users that a page
                they're visiting might not be as legitimate as they thought.</p>
            <p>But still: if on your website, you are linking to urls originating
                from user input, it'd be a good idea to have an eye on urls
                containing internationalized domain names.</p>
            <h2>How to identify IDN urls using the Url class</h2>
            <p>The Url class has the handy <code>hasIdn</code> method:</p>
            <pre><code class="language-php">$legitUrl = Url::parse('https://www.apple.com');
$seemsLegitUrl = Url::parse('https://www.аpple.com');

var_dump($legitUrl-&gt;hasIdn());              // bool(false)
var_dump($seemsLegitUrl-&gt;hasIdn());         // bool(true)

var_dump($legitUrl-&gt;__toString());          // string(21) "https://www.apple.com"
var_dump($seemsLegitUrl-&gt;__toString());     // string(28) "https://www.xn--pple-43d.com"</code></pre>
            <p>So you see, it's very easy to identify IDN urls with it. Of course
                there are many legitimate IDN domains, so you might not want to
                automatically block all of them. I'd suggest you could put some kind
                of monitoring in place that notifies you about users posting links
                to IDNs.</p>
            <p>Maybe you're operating in a country where IDNs are very common. Maybe
                in that case you can find a way to automatically sort out legitimate
                uses from your area.</p>
        </article>
        <script type="application/ld+json">{"@context":"https:\/\/schema.org","@type":"BlogPosting","headline":"Prevent Homograph Attacks using the crwlr\/url Package","author":{"@type":"Person","name":"Christian Olear","alternateName":"Otsch"},"description":"Homograph attacks are using internationalized domain names (IDN) for malicious links including domains that look like trusted organizations. You can use the crwlr Url class to detect and monitor urls containing IDNs in your user's input.","dateCreated":"2022-01-19","datePublished":"2022-01-19","keywords":"homograph, attack, security, idn, internationalized domain names, prevention, url, uri"}</script>
    </div>
</main>
<footer>
    <div class="inner">
        <div class="tiles">
            <div class="tile-hidden">
                <p class="no-margin-top">Follow crwlr.software on</p>
                <a href="https://github.com/crwlrsoft" target="_blank" rel="noopener"title="crwlr.software on Github">Github</a>
                <a href="https://twitter.com/crwlrsoft" target="_blank" rel="noopener"title="Follow crwlr.software on Twitter!">Twitter</a>
            </div>
            <div class="tile-hidden">
                <a href="/privacy">Privacy</a>
                <a href="/imprint">Imprint</a>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
