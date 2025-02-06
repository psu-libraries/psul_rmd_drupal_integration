
## Usage

## Use this in a TWIG template

  ```twig
{%
  include 'psul_rmd_drupal_integration:orcid_link' with {
    orcid: '0000-0002-2771-9344',
    authenticated: true,
    format: full
  }
%}

{%
  include 'psul_rmd_drupal_integration:orcid_link' with {
    orcid: '0000-0002-2771-9344',
    authenticated: false,
    logo_color: 'mono',
    format: 'compact',
  }
%}

<p class="text-bg-primary"> Dictumst nostra taciti augue nibh mi.
  {%
    include 'psul_rmd_drupal_integration:orcid_link' with {
      orcid: '0000-0002-0000-9344',
      authenticated: true,
      logo_color: 'reversed',
      format: 'inline',
      inline_text: 'View ORCID record',
    }
  %}  Et volutpat mattis vestibulum vulputate bibendum morbi massa.
</p>
```

## Use this in a Drupal render array.

```php
$render_array = [
  '#type' => 'component',
  '#component' => 'psul_rmd_drupal_integration:orcid_link',
  '#props' => [
    'orcid' => '0000-0002-0000-9344',
    'authenticated' => TRUE,
    'format' => 'compact',
  ],
];
```
## Links
- https://info.orcid.org/documentation/integration-guide/orcid-id-display-guidelines/
- https://orcid.filecamp.com/s/o/BCaXKvfctKWKKh30
