<?php

namespace Tests\Feature;

use EscolaLms\Scorm\Tests\ScormTestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use EscolaLms\Scorm\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class ScormAdminApiTest extends TestCase
{
    use DatabaseTransactions, ScormTestTrait;

    public function test_content_upload()
    {
        $response = $this->uploadScorm();
        $data = $response->getData();

        $response->assertStatus(200);
        $this->assertEquals($data->data->scormData->scos[0]->title, "Employee Health and Wellness (Sample Course)");
    }

    public function test_content_upload_invalid_data()
    {
        $this->actingAs($this->user, 'api')
            ->json('POST', '/api/admin/scorm/upload', [
                'zip' => UploadedFile::fake()->create('file.zip', 100, 'application/zip'),
            ])->assertJson([
                'success' => false,
                'message' => "invalid_scorm_archive_message"
            ]);
    }

    public function test_content_upload_invalid_data_format()
    {
        $this->actingAs($this->user, 'api')
            ->json('POST', '/api/admin/scorm/upload', [
                'zip' => UploadedFile::fake()->create('file.svg', 100, 'application/svg'),
            ])->assertJson([
                'message' => 'The given data was invalid.',
            ]);
    }

    public function test_content_parse()
    {
        $zipFile = $this->getUploadScormFile();
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/scorm/parse', [
            'zip' => $zipFile,
        ]);

        $data = $response->getData();

        $response->assertStatus(200);
        $this->assertEquals($data->data->scos[0]->title, "Employee Health and Wellness (Sample Course)");
    }

    public function test_delete_scorm()
    {
        $response = $this->uploadScorm();
        $data = $response->getData();
        $scormData = $data->data->scormData;
        $model = $data->data->model;
        $path = 'scorm' . DIRECTORY_SEPARATOR . $scormData->version . DIRECTORY_SEPARATOR . $scormData->hashName;

        $response = $this->actingAs($this->user, 'api')->json('DELETE', '/api/admin/scorm/' . $model->id);

        $response->assertStatus(200);
        $this->assertFalse(Storage::disk(config('scorm.disk'))->exists($path));
        $this->assertDatabaseMissing('scorm', [
            'id' => $model->id,
            'uuid' => $model->uuid,
        ]);
        $this->assertDatabaseMissing('scorm_sco', [
            'uuid' => $scormData->scos[0]->uuid,
        ]);
    }

    public function test_get_model_list()
    {
        $response = $this->uploadScorm();
        $data = $response->getData();

        $response = $this->actingAs($this->user, 'api')->get('/api/admin/scorm');
        $list = $response->getData();

        $found = array_filter($list->data->data, function ($item) use ($data) {
            if ($item->uuid === $data->data->model->uuid) {
                return true;
            }
            return false;
        });

        $this->assertCount(1, $found);
    }

    public function test_player_view()
    {
        $response = $this->uploadScorm();
        $data = $response->getData();

        $response = $this->actingAs($this->user, 'api')->get('/api/scorm/play/' . $data->data->scormData->scos[0]->uuid);
        $response->assertStatus(200);
    }
}
