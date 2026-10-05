import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Input } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { EmptyState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { qk, useRoles, useUnits } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { masterService } from '@/services/masterService'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState, type FormEvent } from 'react'

const SATUAN_KOSONG = { code: '', name: '' }

/** Master data aplikasi: peran pengguna dan satuan pekerjaan. */
export function SettingsPage() {
  const { user, punyaPeran } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const adminMode = punyaPeran('ADMIN')
  const { data: roles, isLoading: memuatRoles } = useRoles()
  const { data: units, isLoading: memuatUnits } = useUnits()

  const [formSatuan, setFormSatuan] = useState(false)
  const [satuan, setSatuan] = useState(SATUAN_KOSONG)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const bukaFormSatuan = () => {
    setSatuan(SATUAN_KOSONG)
    setErrors({})
    setFormSatuan(true)
  }

  const simpanSatuan = useMutation({
    mutationFn: masterService.createUnit,
    onSuccess: async () => {
      toast.sukses('Satuan pekerjaan berhasil ditambahkan.')
      await queryClient.invalidateQueries({ queryKey: qk.units })
      setFormSatuan(false)
    },
    onError: (err) => {
      const validasi = errorValidasi(err)
      setErrors(validasi)
      toast.gagal(Object.keys(validasi).length > 0 ? 'Periksa kembali data yang kamu masukkan.' : pesanError(err))
    },
  })

  const kirimSatuan = (event: FormEvent) => {
    event.preventDefault()
    const validasi: Record<string, string> = {}
    const payload = { code: satuan.code.trim(), name: satuan.name.trim() }

    if (!payload.code) validasi.code = 'Kode satuan wajib diisi.'
    if (!payload.name) validasi.name = 'Nama satuan wajib diisi.'

    setErrors(validasi)

    if (Object.keys(validasi).length > 0) return

    simpanSatuan.mutate(payload)
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h2 className="text-lg font-semibold text-ink">Pengaturan</h2>
        <p className="text-xs text-muted">Profil akun dan master data aplikasi.</p>
      </div>

      <Card title="Profil Akun">
        <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs text-muted">Nama</dt>
            <dd>{user?.name}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted">Email</dt>
            <dd>{user?.email ?? '-'}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted">Peran</dt>
            <dd>{user?.role?.name ?? user?.role_code}</dd>
          </div>
        </dl>
      </Card>

      <Card title="Peran Pengguna" description="Hak akses aplikasi ditentukan oleh peran berikut." bodyClassName="pt-0">
        {memuatRoles ? (
          <LoadingState />
        ) : !roles || roles.length === 0 ? (
          <EmptyState />
        ) : (
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Nama Peran</Th>
                </tr>
              </thead>
              <tbody>
                {roles.map((role) => (
                  <tr key={role.id}>
                    <Td className="font-medium">{role.code}</Td>
                    <Td className="text-muted">{role.name}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        )}
      </Card>

      <Card
        title="Satuan Pekerjaan"
        description="Master data satuan yang dipakai pada data pekerjaan."
        action={
          adminMode && (
            <Button size="sm" icon={<Plus className="size-4" />} onClick={bukaFormSatuan}>
              Tambah Satuan
            </Button>
          )
        }
        bodyClassName="pt-0"
      >
        {memuatUnits ? (
          <LoadingState />
        ) : !units || units.length === 0 ? (
          <EmptyState judul="Belum ada satuan" pesan={adminMode ? 'Tambahkan satuan pekerjaan baru.' : undefined} />
        ) : (
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Nama Satuan</Th>
                </tr>
              </thead>
              <tbody>
                {units.map((unit) => (
                  <tr key={unit.id}>
                    <Td className="font-medium">{unit.code}</Td>
                    <Td className="text-muted">{unit.name}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        )}
      </Card>

      <Modal
        open={formSatuan}
        onClose={() => setFormSatuan(false)}
        title="Tambah Satuan Pekerjaan"
        description="Satuan baru langsung bisa dipilih saat menambah data pekerjaan."
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setFormSatuan(false)} disabled={simpanSatuan.isPending}>
              Batal
            </Button>
            <Button form="form-satuan" type="submit" loading={simpanSatuan.isPending}>
              Simpan
            </Button>
          </>
        }
      >
        <form id="form-satuan" className="grid gap-4" onSubmit={kirimSatuan} noValidate>
          <Input
            label="Kode Satuan"
            required
            placeholder="Contoh: m3"
            maxLength={20}
            value={satuan.code}
            onChange={(event) => setSatuan({ ...satuan, code: event.target.value })}
            error={errors.code}
          />
          <Input
            label="Nama Satuan"
            required
            placeholder="Contoh: Meter Kubik"
            maxLength={80}
            value={satuan.name}
            onChange={(event) => setSatuan({ ...satuan, name: event.target.value })}
            error={errors.name}
          />
        </form>
      </Modal>
    </div>
  )
}
