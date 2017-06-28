<?php
include "functions.php"; 

if ( isset($_GET['crawl']) ) {
	$requestCrawl = $_GET['crawl'];
	if ((substr($requestCrawl, 0, 7) !== 'http://') && (substr($requestCrawl, 0, 8) !== 'https://') ) {
		$requestCrawl = 'http://' . $requestCrawl;
	}
	
	$page = getCurl($requestCrawl);
	$errorCount = 0;
	$warningCount = 0;
	$siteAnchors = array();
	$subPages = array();
	
	// Crawl the anchors in the root
	if ( $page ) {
		$sitePages = getSitePages( array('pageHTML'=>$page,'siteURL'=>$requestCrawl) );
			
		// Collect all the site anchors thus far...
		foreach ( $sitePages as $p ) {
			$siteAnchors[$p['href']] = true;
		} 
					
		// Loop through pages to start error check
		foreach ( $sitePages as $key => $val ) {
			
			$sitePages[$key]['results'] = checkPage($val);
			
			if ( $sitePages[$key]['results']['Title Tag']['value'] === '301 Moved Permanently' || $sitePages[$key]['results']['Title Tag']['value'] === '302 Found' ) {
				// Invalid page...
				unset($sitePages[$key]);
			} else {				
				foreach ( $sitePages[$key]['results'] as $r ) {
					if ( $r['valid'] === false ) {
						if ( $r['type'] === 'error' ) {
							$errorCount++;
						} else {
							$warningCount++;
						}
					}
				}				
			}
		}
		
		foreach ( $sitePages[0]['favicons'] as $favicon ) {
			if ( isset($favicon['valid']) ) {
				$errorCount++;
			}
		}
		
		// Loop through any sub pages
		if ( count($subPages) > 0 ) {
			foreach( $subPages as $spkey=>$spval ) {
			
				$subPages[$spkey]['results'] = checkPage($spval);
				foreach ( $subPages[$spkey]['results'] as $r ) {
					if ( $r['valid'] === false ) {
						if ( $r['type'] === 'error' ) {
							$errorCount++;
						} else {
							$warningCount++;
						}
					}
				}
			}
		}
		
		// Check for any duplicate Title or Descriptions amongst pages...
		$pageTitles = array();
		$pageDescriptions = array();
		foreach( $sitePages as $k=>$v ) {
			if ( $v['results']['Title Tag']['valid'] && isset($pageTitles[$v['results']['Title Tag']['value']]) ) {
				$warningCount++;
				$sitePages[$k]['results']['Title Tag']['valid'] = false;
				$sitePages[$k]['results']['Title Tag']['type'] = 'warning';
				$sitePages[$k]['results']['Title Tag']['value'] = 'Duplicate title from ' .$pageTitles[$v['results']['Title Tag']['value']]. ':<br>' . $v['results']['Title Tag']['value'];
			} else if ( $v['results']['Title Tag']['valid'] ) {
				$pageTitles[$v['results']['Title Tag']['value']] = $v['name'];
			}
			
			if ( isset($v['results']['Description Tag']) && $v['results']['Description Tag']['valid'] && isset($pageDescriptions[$v['results']['Description Tag']['value']]) ) {
				$warningCount++;
				$sitePages[$k]['results']['Description Tag']['valid'] = false;
				$sitePages[$k]['results']['Description Tag']['type'] = 'warning';
				$sitePages[$k]['results']['Description Tag']['value'] = 'Duplicate description from ' .$pageDescriptions[$v['results']['Description Tag']['value']]. ':<br>' . $v['results']['Description Tag']['value'];
			} else if ( isset($v['results']['Description Tag']) && $v['results']['Description Tag']['valid'] ) {
				$pageDescriptions[$v['results']['Description Tag']['value']] = $v['name'];
			}
		}
	}
}

?>


<!doctype html>
<html lang="en-US">
<head>
	<meta charset="utf-8" />
	<title>Site Crawler</title>
	
	<link href="https://fonts.googleapis.com/css?family=Roboto:300,700" rel="stylesheet" /> 
	<link href="style.css" rel="stylesheet" />
</head>
<body>
	<?php if ( empty($_GET['crawl']) || !isset($page) ) { ?>
	
		<h1>Site Crawler</h1>
		<form method="get">
			<label>Enter a full (http://) address to check:</label>
			<input type="text" name="crawl" />
			<input type="submit" value="Crawl" />
		</form>
	
	<?php } else {
		?>
		
		<div class="siteOverview">
			<h3>Overview:</h3>
			<table>
				<tr>
					<td>Website:</td>
					<td><a href="<?= $_GET['crawl'] ?>" target="_blank"><?= $_GET['crawl'] ?></a></td>
				</tr>
				<tr>
					<td>Pages Found:</td>
					<td><?= count($sitePages); ?></td>
				</tr>
				<tr>
					<td>Models / QDH Found:</td>
					<td><?= count($subPages); ?></td>
				</tr>
				<tr>
					<td>Errors:</td>
					<td class="<?php if ( $errorCount === 0 ) { echo 'valid'; } else { echo 'invalid'; } ?>"><?= $errorCount ?></td>
				</tr>
				<tr>
					<td>Warnings:</td>
					<td class="<?php if ( $warningCount === 0 ) { echo 'valid'; } else { echo 'warning'; } ?>"><?= $warningCount ?></td>
				</tr>
				<tr>
					<td colspan="2">Favicons:</td>
				</tr>
				<tr>
					<td colspan="2" style="text-align:center;">
						<?php foreach ( $sitePages[0]['favicons'] as $key=>$favicon ) { ?>
							
							<?php if ( !isset($favicon['valid']) ) { ?>
								<div class="ficon">
									<img src="<?= $favicon ?>" alt=""/>
									<p><?= $key ?></p>
								</div>
							<?php } else { ?>
								<div class="ficon">
									<img src="invalid.png" alt=""/>
									<p><?= $key ?></p>
								</div>
							<?php } ?>
						
						<?php } ?>
					</td>
				</tr>
			</table>
			<a class="goback" href="/">Go Back</a>
		</div>
	
	
		<div class="pagesWrapper"><!--
	
		<?php $pageCount = 0; ?>
		<?php foreach ( $sitePages as $page ) { ?>
		
			--><div class="pageBlock">
				<h3><a href="<?= $page['href'] ?>" target="_blank"><?= $page['name'] ?><br/><span><?= $page['href'] ?></span></a></h3>
				<div class="pageBlockResults">
					<?php foreach ( $page['results'] as $key=>$val ) { 
					
						$className = "";
						if ( !$val['valid'] && $val['type'] === 'error' ) {
							$className = 'invalid';
						} else if ( !$val['valid'] && $val['type'] === 'warning' ) {
							$className = 'warning';
						}
					
					?>
					
						<div class="tr <?= $className ?>"><!--
							--><div class="td"><?= $key ?></div><!--
							--><div class="td val <?= $key ?>"><?= $val['value'] ?></div><!--
						--></div>
					
					<?php } ?>
				</div>
			</div><!--
		
			<?php
			$pageCount++;
			if ( $pageCount % 3 === 0 ) {
				echo '--><hr style="clear:both;display:block;width:100%;float:none;margin:0px 0px;height:1px;"/><!--';
			}
			?>
		
		<?php }	// end page loop ?>
		
		--></div>
		
		<?php if ( count($subPages) > 0 ) { ?>
			<h4>Sub Pages<span>Like Models, QDH, or other sub pages in Residences.</span></h4>
			<div class="pagesWrapper"><!--
	
			<?php $pageCount = 0; ?>
			<?php foreach ( $subPages as $page ) { ?>
		
				--><div class="pageBlock">
					<h3><a href="<?= $page['href'] ?>" target="_blank"><?= $page['name'] ?><br/><span><?= $page['href'] ?></span></a></h3>
					<div class="pageBlockResults">
						<?php foreach ( $page['results'] as $key=>$val ) { 
					
							$className = "";
							if ( !$val['valid'] && $val['type'] === 'error' ) {
								$className = 'invalid';
							} else if ( !$val['valid'] && $val['type'] === 'warning' ) {
								$className = 'warning';
							}
					
						?>
					
							<div class="tr <?= $className ?>"><!--
								--><div class="td"><?= $key ?></div><!--
								--><div class="td val <?= $key ?>"><?= $val['value'] ?></div><!--
							--></div>
					
						<?php } ?>
					</div>
				</div><!--
		
				<?php
				$pageCount++;
				if ( $pageCount % 3 === 0 ) {
					echo '--><hr style="clear:both;display:block;width:100%;float:none;margin:0px 0px;height:1px;"/><!--';
				}
				?>
		
			<?php }	// end subpage loop ?>
		
			--></div>
		
		<?php } // end if subpages ?>
		
	<?php } // end if ?>

	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script>
$('form').submit(function(e) {
	if ( $('form').hasClass('submitting') ) {
		e.preventDefault();
		return false;
	}
	// Remove white space from URL
	var val = $('form').find('input[name="crawl"]').val().replace(/^\s\s*/g, '').replace(/\s\s*$/g, '');
	$('form').find('input[name="crawl"]').val( val );
	$('form').addClass('submitting');
	$('form').find('label').html('Crawling website, please wait. This can take a few moments...');
	$('form').find('input[name="crawl"]').attr('readonly','readonly');
	$('form').find('input[type="submit"]').val('Fetching...');
});
</script>
</body>
</html>