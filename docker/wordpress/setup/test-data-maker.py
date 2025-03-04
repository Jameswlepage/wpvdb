import urllib.request
import xml.etree.ElementTree as ET
import os

base_url = 'https://make.wordpress.org/core/feed/'
output_file = os.path.join(os.path.dirname(__file__), 'test-data.xml')
max_pages = 20

# Create the root element for the combined feed
combined_root = ET.Element('rss', version='2.0')
channel = ET.SubElement(combined_root, 'channel')

page = 1
while page <= max_pages:
    print(f'Fetching page {page} of {max_pages}')
    try:
        url = f'{base_url}?paged={page}'
        response = urllib.request.urlopen(url, timeout=10)
        content = response.read()
    except Exception as e:
        print(f"Error fetching page {page}: {e}")
        break

    page_xml = ET.fromstring(content)
    items = page_xml.findall('.//item')

    if not items:
        print('No more items to fetch, stopping.')
        break

    for item in items:
        # Append each item to the combined channel
        channel.append(item)

    page += 1

# Write combined XML to file
tree = ET.ElementTree(combined_root)
tree.write(output_file, encoding='utf-8', xml_declaration=True)

print(f'Combined feed saved to {output_file}')
